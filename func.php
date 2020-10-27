<?php
    //
    // 関数のみ分割
    //

    // 実行ディレクトリを指定してコマンド実行
    function execute($config){
        // 環境変数としてメタ情報を追加し、コマンド実行
        // 一応メモ executeの作業フォルダは$config['repo_dir']ではなく
        // DeployUtilの置かれている場所 (そのためにrepo_dirを環境変数で渡している)
        $env['REPO_NAME'] = $config['repo_name'];
        $env['REPO_PATH'] = $config['repo_dir'];
        $result = shellExec($config['execute'], NULL, $env);

        // 一応実行結果を別のログに流す
        logging(json_encode($result), "command.log");
    }

    // 外部コマンド実行関数 (proc_open製)
    function shellExec($cmd, $cwd = NULL, $addEnv = NULL){
        $result = [
            'code' => -1,
            'stdout' => "",
            'stderr' => "",
        ];

        // パイプ設定
        $discriptors = [
            0 => array('pipe', 'r'), //stdin
            1 => array('pipe', 'w'), //stdout
            2 => array('pipe', 'w'), //stderr
        ];

        // 環境変数が渡されていた場合は$_ENVにマージ
        $env = $_ENV;
        if($addEnv != NULL){
            $env = array_merge($env, $addEnv);
        }

        // プロセスを開く
        $procHandler = proc_open($cmd, $discriptors, $pipes, $cwd, $env);
        if($procHandler === FALSE){
            return $result;
        }
    
        // 各ストリームの出力を待機し、それぞれ変数に格納
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
    
        // プロセスハンドラを閉じる
        $result = [
            'code' => proc_close($procHandler),
            'out' => trim($stdout),
            'err' => trim($stderr),
        ];

        return $result;
    }
    
    // コンフィグで指定されたトリガーと照合
    function checkSameAction($config, $requestJson){
        // ref (リポジトリへの参照, pushやtagなど)
        if(isset($config['ref'], $requestJson['ref'])){
            // TODO: ワイルドカード対応させたい
            return $config['ref'] == $requestJson['ref'];
        }

        // action (リリースなど)
        if(isset($config['action'], $requestJson['action'])){
            return $config['action'] == $requestJson['action'];
        }

        // どちらにも当てはまらなければ照合失敗とする
        return FALSE;
    }

    // レスポンスを返しつつ、エラーメッセージをログに吐き出す
    function throwErrorResponse($responseCode = -1, $message = "", $filename="error.log"){
        // CGI版はheader関数が使えないので、php_sapi_name()で動作モードを確認
        // 取得した値に"cgi"が含まれていればheader関数は実行しない
        $phpMode = strtolower(php_sapi_name());
        if(strpos($phpMode, "cgi") === FALSE){
            header('HTTP', true, $responseCode);
        }
        logging("mode: $phpMode  reponse: $responseCode:  message: $message");
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