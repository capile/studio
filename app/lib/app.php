<?php



class app {

	/**
	 * Git webhook
	 */
    public static function webhookPull()
    {
        $secret = tdz::getApp()->studio['secret'];
        if($secret) {
            if(isset($_SERVER['HTTP_X_HUB_SIGNATURE']) && ($p=file_get_contents('php://input'))) {
                list($method, $sign) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2);
                $hash = hash_hmac($method, $p, $secret);
                if($hash==$sign) {
                    if(file_exists(TDZ_DOCUMENT_ROOT.'/.git')) {
                        chdir(TDZ_DOCUMENT_ROOT);
                        $cmd = TDZ_APP_ROOT."/gitpull.sh \"".TDZ_DOCUMENT_ROOT.'"';
                        exec($cmd, $r);
                        tdz::log(TDZ_DOCUMENT_ROOT.'$ '.$cmd, $r);
                        header('X-Response: OK');
                        tdz::debug(implode("\n", $r));
                    } else {
                        tdz::log("[ERRO] repositório não existe: ".TDZ_DOCUMENT_ROOT);
                    }
                } else {
                    tdz::log("[ERRO] Problemas na assinatura do payload ({$method}):\n{$hash}\n{$sign}", $_SERVER);
                }
            }
            exit();
        } else {
            tdz::log("[ERRO] que URL é essa? {$_SERVER['REQUEST_URI']} ?");
        }
        return false;
    }

	
}