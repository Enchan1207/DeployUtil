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
        logging("Request hash is set: $requestHash");
    }

    // コンフィグを回す
    foreach ($configJson['deployment_targets'] as $config) {
        // リポジトリ名
        $isSameRepo = $config['repo_name'] == $requestJson['repository']['full_name'];

        // アクション
        // TODO: アクション別にrefの文字列はある程度確定するから、
        // jsonで全部指定しなくてもいいようにはしたい
        logging($requestJson['ref']." ::: ".$_SERVER['X-GITHUB-EVENT']);
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
        logging("at config " . $config['repo_name'] . " -> " . $config['ref']);

        // 合致する場合はコマンドを実行
        if ($isSameRepo && $isSameAction && $isVerified){
            executeAt($config['repo_dir'], $config['execute']);
            logging("Command had been executed: ".$config['repo_dir']." ".$config['execute']);
        } else {
            logging("Command had not been executed.");
        }
    }

    exit;

    // 実行ディレクトリを指定してコマンド実行
    function executeAt($dir = "~/", $cmd = ""){
        // 実行コマンドとディレクトリをファイルにはきだす
        file_put_contents("command.log", $dir." : ".$cmd);

        // ディレクトリを移動してコマンド実行
        system("cd $dir;".$cmd);
    }

    // レスポンスを返しつつ、エラーメッセージをログに吐き出す
    function throwErrorResponse($responseCode = -1, $message = "", $filename="error.log"){
        header('HTTP', true, $responseCode);
        logging("$responseCode: $message");
    }

    // ログファイルに書き出す
    function logging($message, $filename = "system.log"){
        // 記録するコンテンツを生成
        date_default_timezone_set("Asia/Tokyo");
        $content = date("Y-m-d H:i:s")." message: ".$message."\n";
        
        // 書き込み
        file_put_contents($filename, $content, FILE_APPEND);
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