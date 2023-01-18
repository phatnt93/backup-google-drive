<?php 

class Common 
{
    public function valueByKey($params, string $key, $default = ''){
        if (is_array($params) && array_key_exists($key, $params)) {
            return $params[$key];
        }elseif (is_object($params) && property_exists($params, $key)) {
            return $params->{$key};
        }
        return $default;
    }

    public function configValueByPath(array $params, string $path, $default = ''){
        $pathArr = array_filter(explode('.', $path));
        if (count($pathArr) > 0 && array_key_exists($pathArr[0], $params)) {
            $data = $params[$pathArr[0]];
            if (count($pathArr) == 1) {
                return $data;
            }elseif(is_array($data)){
                array_shift($pathArr);
                $pathArr = array_values($pathArr);
                return $this->configValueByPath($data, implode('.', $pathArr), $default);
            }
        }
        return $default;
    }

    public function ensureDirPath(string $path){
        if (!file_exists($path)) {
            mkdir($path, 0777);
        }
    }

    public function writeLog(string $msg){
        $this->ensureDirPath(LOG_PATH);
        $msg = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
        $logPath = LOG_PATH . DIRECTORY_SEPARATOR . date('Ymd');
        file_put_contents($logPath, $msg, FILE_APPEND);
    }

    public function convertJson(string $data, $isArray = true){
        $dt = json_decode($data, $isArray);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $dt;
        }
        return false;
    }

    public function deleteDir($pathDir) {
        if (!file_exists($pathDir)) {
            return true;
        }
        if (!is_dir($pathDir)) {
            return unlink($pathDir);
        }
        foreach (scandir($pathDir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!self::deleteDir($pathDir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($pathDir);
    }
}
