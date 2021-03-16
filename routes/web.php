<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'PocController@index');
Route::get('/webhook', 'PocController@hook');

use Illuminate\Support\Facades\Storage;

Route::get('put', function() {
    Storage::disk('google')->put('test.txt', 'Hello World');
    return 'File was saved to Google Drive';
});

Route::get('put-existing', function() {
    $filename = 'laravel.png';
    $filePath = public_path($filename);
    $fileData = File::get($filePath);

    Storage::disk('google')->put($filename, $fileData);
    return 'File was saved to Google Drive';
});

Route::get('list', function() {
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    //return $contents->where('type', '=', 'dir'); // directories
    return $contents->where('type', '=', 'file'); // files
});

Route::get('list-folder-contents', function() {
    // The human readable folder name to get the contents of...
    // For simplicity, this folder is assumed to exist in the root directory.
    $folder = 'Test Dir';

    // Get root directory contents...
    $contents = collect(Storage::disk('google')->listContents('/', false));

    // Find the folder you are looking for...
    $dir = $contents->where('type', '=', 'dir')
        ->where('filename', '=', $folder)
        ->first(); // There could be duplicate directory names!

    if ( ! $dir) {
        return 'No such folder!';
    }

    // Get the files inside the folder...
    $files = collect(Storage::disk('google')->listContents($dir['path'], false))
        ->where('type', '=', 'file');

    return $files->mapWithKeys(function($file) {
        $filename = $file['filename'].'.'.$file['extension'];
        $path = $file['path'];

        // Use the path to download each file via a generated link..
        // Storage::disk('google')->get($file['path']);

        return [$filename => $path];
    });
});

Route::get('get', function() {
    $filename = 'test.txt';

    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    $file = $contents
        ->where('type', '=', 'file')
        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
        ->first(); // there can be duplicate file names!

    //return $file; // array with file info

    $rawData = Storage::disk('google')->get($file['path']);

    return response($rawData, 200)
        ->header('ContentType', $file['mimetype'])
        ->header('Content-Disposition', "attachment; filename=$filename");
});

Route::get('put-get-stream', function() {
    // Use a stream to upload and download larger files
    // to avoid exceeding PHP's memory limit.

    // Thanks to @Arman8852's comment:
    // https://github.com/ivanvermeyen/laravel-google-drive-demo/issues/4#issuecomment-331625531
    // And this excellent explanation from Freek Van der Herten:
    // https://murze.be/2015/07/upload-large-files-to-s3-using-laravel-5/

    // Assume this is a large file...
    $filename = 'laravel.png';
    $filePath = public_path($filename);

    // Upload using a stream...
    Storage::disk('google')->put($filename, fopen($filePath, 'r+'));

    // Get file listing...
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    // Get file details...
    $file = $contents
        ->where('type', '=', 'file')
        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
        ->first(); // there can be duplicate file names!

    //return $file; // array with file info

    // Store the file locally...
    //$readStream = Storage::disk('google')->getDriver()->readStream($file['path']);
    //$targetFile = storage_path("downloaded-{$filename}");
    //file_put_contents($targetFile, stream_get_contents($readStream), FILE_APPEND);

    // Stream the file to the browser...
    $readStream = Storage::disk('google')->getDriver()->readStream($file['path']);

    return response()->stream(function () use ($readStream) {
        fpassthru($readStream);
    }, 200, [
        'Content-Type' => $file['mimetype'],
        //'Content-disposition' => 'attachment; filename="'.$filename.'"', // force download?
    ]);
});

Route::get('create-dir', function() {
    Storage::disk('google')->makeDirectory('Test Dir');
    return 'Directory was created in Google Drive';
});

Route::get('create-sub-dir', function() {
    // Create parent dir
    Storage::disk('google')->makeDirectory('Test Dir');

    // Find parent dir for reference
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    $dir = $contents->where('type', '=', 'dir')
        ->where('filename', '=', 'Test Dir')
        ->first(); // There could be duplicate directory names!

    if ( ! $dir) {
        return 'Directory does not exist!';
    }

    // Create sub dir
    Storage::disk('google')->makeDirectory($dir['path'].'/Sub Dir');

    return 'Sub Directory was created in Google Drive';
});

Route::get('put-in-dir', function() {
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    $dir = $contents->where('type', '=', 'dir')
        ->where('filename', '=', 'Test Dir')
        ->first(); // There could be duplicate directory names!

    if ( ! $dir) {
        return 'Directory does not exist!';
    }

    Storage::disk('google')->put($dir['path'].'/test.txt', 'Hello World');

    return 'File was created in the sub directory in Google Drive';
});

Route::get('newest', function() {
    $filename = 'test.txt';

    Storage::disk('google')->put($filename, \Carbon\Carbon::now()->toDateTimeString());

    $dir = '/';
    $recursive = false; // Get subdirectories also?

    $file = collect(Storage::disk('google')->listContents($dir, $recursive))
        ->where('type', '=', 'file')
        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
        ->sortBy('timestamp')
        ->last();

    return Storage::disk('google')->get($file['path']);
});

Route::get('delete', function() {
    $filename = 'test.txt';

    // First we need to create a file to delete
    Storage::disk('google')->makeDirectory('Test Dir');

    // Now find that file and use its ID (path) to delete it
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    $file = $contents
        ->where('type', '=', 'file')
        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
        ->first(); // there can be duplicate file names!

    Storage::disk('google')->delete($file['path']);

    return 'File was deleted from Google Drive';
});

Route::get('delete-dir', function() {
    $directoryName = 'test';

    // First we need to create a directory to delete
    Storage::disk('google')->makeDirectory($directoryName);

    // Now find that directory and use its ID (path) to delete it
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    $directory = $contents
        ->where('type', '=', 'dir')
        ->where('filename', '=', $directoryName)
        ->first(); // there can be duplicate file names!

    Storage::disk('google')->deleteDirectory($directory['path']);

    return 'Directory was deleted from Google Drive';
});

Route::get('rename-dir', function() {
    $directoryName = 'test';

    // First we need to create a directory to rename
    Storage::disk('google')->makeDirectory($directoryName);

    // Now find that directory and use its ID (path) to rename it
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));

    $directory = $contents
        ->where('type', '=', 'dir')
        ->where('filename', '=', $directoryName)
        ->first(); // there can be duplicate file names!

    Storage::disk('google')->move($directory['path'], 'new-test');

    return 'Directory was renamed in Google Drive';
});

Route::get('share', function() {
    $filename = 'test.txt';

    // Store a demo file
    Storage::disk('google')->put($filename, 'Hello World');

    // Get the file to find the ID
    $dir = '/';
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents($dir, $recursive));
    $file = $contents
        ->where('type', '=', 'file')
        ->where('filename', '=', pathinfo($filename, PATHINFO_FILENAME))
        ->where('extension', '=', pathinfo($filename, PATHINFO_EXTENSION))
        ->first(); // there can be duplicate file names!

    // Change permissions
    // - https://developers.google.com/drive/v3/web/about-permissions
    // - https://developers.google.com/drive/v3/reference/permissions
    $service = Storage::disk('google')->getAdapter()->getService();
    $permission = new \Google_Service_Drive_Permission();
    $permission->setRole('reader');
    $permission->setType('anyone');
    $permission->setAllowFileDiscovery(false);
    $permissions = $service->permissions->create($file['basename'], $permission);

    return Storage::disk('google')->url($file['path']);
});

Route::get('export/{basename}', function ($basename) {
    $service = Storage::disk('google')->getAdapter()->getService();
    $mimeType = 'application/pdf';
    $export = $service->files->export($basename, $mimeType);

    return response($export->getBody(), 200, $export->getHeaders());
});
