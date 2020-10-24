<?php
    //
    // Webhookユーティリティ
    //

    // GitHubからのアクセスでなければ400 ここは一般人によるアクセスを遮断するためのもの
    $get = htmlValidate($_GET);
    if(empty($get['from']) || strtolower($get['from']) != "github"){
        throwErrorResponse(403, "");
        exit;
    }

    // リクエストボディを取得
    $requestJson = getJson("php://input");
    if($requestJson ==  NULL || isset($requestJson['decode_error'])){
        throwErrorResponse(400, "<p>Invalid request body has detected.This utility only supported request from GitHub Webhook.</p>\n");
        print("<p>Invalid request body has detected.This utility only supported request from GitHub Webhook.</p>\n");
        exit;
    }
    
    // config.jsonを読み込む
    $configPath = "config.json";
    $configJson = getJson($configPath);
    if(isset($configJson['decode_error'])){
        throwErrorResponse(401, "Couldn't read config.json.");
        exit;
    }

    // 秘密鍵のハッシュが投げられていれば取得
    if(isset($_SERVER['HTTP_X_HUB_SIGNATURE'])){
        $requestHash = substr($_SERVER['HTTP_X_HUB_SIGNATURE'], 4);
    }

    // リクエストボディのjsonに適合するものがconfigにあればexecute以下を実行
    foreach ($configJson['deployment_targets'] as $config) {
        // リポジトリ名
        $isSameRepo = $config['repo_name'] == $requestJson['repository']['full_name'];

        // アクション
        // TODO: アクション別にrefの文字列はある程度確定するから、
        // jsonで全部指定しなくてもいいようにはしたい
        $isSameAction = $config['ref']== $requestJson['ref'];

        // 秘密鍵ハッシュ (Nullable)
        $isVerified = TRUE;
        if(isset($config['secret_hash'])){
            $isVerified = FALSE;
            if(isset($requestHash)){
                $isVerified = $requestHash == $config['secret_hash'];
            }

            // 秘密鍵ハッシュがなんらかの理由で正しくベリファイされなかった場合
            // (e.g. config.jsonで未定義 or 設定値と合わない)
            if(!$isVerified){
                throwErrorResponse(401, "Couldn't verify secret key hash.");
                exit;
            }
        }

        // 諸々をログに吐き出す
        throwErrorResponse(-1, "at config " . $config['repo_name'] . " -> " . $config['ref'] . ": Command had not been executed.");

        // 合致する場合はコマンドを実行
        if ($isSameRepo && $isSameAction && $isVerified){
            executeAt($config['repo_dir'], $config['execute']);
        } else {
            throwErrorResponse(-1, "at config " . $config['repo_name'] . " -> " . $config['ref'] . ": Command had not been executed.");
        }
    }

    exit;

    // 実行ディレクトリを指定してコマンド実行
    function executeAt($dir = "~/", $cmd = ""){
        // 実行コマンドとディレクトリをファイルにはきだす
        file_put_contents("system.log", $dir." : ".$cmd);

        // ディレクトリを移動してコマンド実行
        system("cd $dir;".$cmd);
    }

    // レスポンスを返しつつ、エラ〜メッセージをログに吐き出す
    function throwErrorResponse($responseCode = -1, $message = "", $filename="error.log"){
        if($responseCode > 0){
            header('HTTP', true, $responseCode);
        }
        file_put_contents($filename, "timestamp: " . time() . " code: $responseCode message: $message\n", FILE_APPEND);
    }

    // ファイル名を指定してjsonを読み込む エラー時はキーdecode_errorを載せて返す
    function getJson($path){
        $requestJsonRaw = "{\"decode_error\": \"couldn't decode or no such file.\"}";
        try {
            $requestJsonRaw = file_get_contents($path);
            if (!$requestJsonRaw) {
                throw new Exception("json fetch error");
            }
        } catch (Exception $e) {
            // print($e);
        } finally {
            $requestJson = json_decode($requestJsonRaw, true);
            return $requestJson;
        }
    }

    // htmlバリデーション
    function htmlValidate($source){
        foreach ($source as $key => $value) {
            $arr[$key] = htmlspecialchars($value);
        }
        return $arr;
    }
?>