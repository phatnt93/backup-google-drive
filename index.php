<?php

/**
 * Backup google drive
 * 
 * CMD: php index.php [file_path]
 * EX: php index.php /demo/demo.tar.gz
 */
use Google\Service\Drive;

define('BASE_PATH', __DIR__);
define('LIB_PATH', BASE_PATH . '/libs');
define('LOG_PATH', BASE_PATH . '/logs');

require BASE_PATH . '/vendor/autoload.php';
require LIB_PATH . '/Common.php';

$common = new Common();

try {
    $filePath = null;
    if (array_key_exists(1, $argv)) {
        $filePath = $argv[1];
    }
    if ($filePath === null || !file_exists($filePath)) {
        throw new \Exception('Not found file path');
    }

    $config = parse_ini_file(BASE_PATH . '/config.ini', true);

    $credentialPath = BASE_PATH . DIRECTORY_SEPARATOR . $common->configValueByPath($config, 'google.auth_file_path');
    if (!file_exists($credentialPath)) {
        throw new \Exception('Not found credentials.json');
    }
    $folderID = $common->configValueByPath($config, 'google.folder_id');

    $client = new \Google\Client();
    $client->setAuthConfig($common->convertJson(file_get_contents($credentialPath)));
    $client->addScope(Drive::DRIVE);

    $driveService = new Drive($client);
    // Check folder exists
    /** @var Google\Service\Drive\FileList */
    $response = $driveService->files->listFiles([
        'pageSize' => 10,
        'q' => "name='" . $common->configValueByPath($config, 'google.folder_name') . "' and mimeType='application/vnd.google-apps.folder'",
        'fields' => 'nextPageToken, files(id, name)'
    ]);
    if (count($response->files) == 0) {
        throw new \Exception('Not found ' . $common->configValueByPath($config, 'google.folder_name') . ' folder');
    }
    /** @var \Google\Service\Drive\DriveFile */
    $backupFolder = $response->files[0];
    // Upload backup file
    $filePathInfo = pathinfo($filePath);
    $fileMetadata = new Drive\DriveFile([
        'name' => $filePathInfo['basename'],
        'parents' => [$backupFolder->id]
    ]);
    $contentUploadFile = file_get_contents($filePath);
    $file = $driveService->files->create($fileMetadata, [
        'data' => $contentUploadFile,
        'mimeType' => mime_content_type($filePath),
        'uploadType' => 'multipart',
        'fields' => 'id'
    ]);
    // Delete latest file if greater than max file
    /** @var Google\Service\Drive\FileList */
    $response = $driveService->files->listFiles([
        'pageSize' => 20,
        'q' => "'" . $backupFolder->id . "' in parents and mimeType='" . mime_content_type($filePath) . "'",
        'fields' => 'nextPageToken, files(fileExtension,id,name,size,mimeType,parents)',
        'orderBy' => 'modifiedTime asc'
    ]);
    if (count($response->getFiles()) > intval($common->configValueByPath($config, 'google.max_file_number'))) {
        $driveService->files->delete($response->getFiles()[0]->id);
    }
    echo 'OK. File ID: ' . $file->id . PHP_EOL;
    $common->writeLog('Backup success. File ID: ' . $file->id);
} catch (\Exception $e){
    $common->writeLog($e->getMessage());
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
die;