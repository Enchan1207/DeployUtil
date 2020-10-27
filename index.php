<?php
    //
    // Webhookユーティリティ
    //
    require_once "func.php";

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

        // コンフィグのrefとリクエストで投げられたrefを比較
        $isSameAction = checkSameAction($config, $requestJson);

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
            execute($config);
            logging("Command had been executed: ".$config['repo_dir']." ".$config['execute']);
        } else {
            logging("Command had not been executed.");
        }
    }
    exit;
?>