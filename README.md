<div align="center">
  <h1>UploadThing Laravel</h1>

  <p>Simple PHP port of <a href="https://docs.uploadthing.com/">UploadThing's UTApi library</a></p>

  ![GitHub License](https://img.shields.io/github/license/Edqe14/laravel-uploadthing)
</div>

## 📥 Installation

```bash
composer require edqe/laravel-uploadthing
```

## 📝 Usage

```php
use UploadThing\UploadThing;

$uploadThing = new UploadThing('your-api-key');

// Upload a file
$file = $request->file('file');
$uploadThing->upload($file);

// Get file list
$uploadThing->listFiles();

// Rename files
$uploadThing->renameFiles([
  ["fileKey" => "fileKey1", "newName" => "newName2"],
  ["fileKey" => "fileKey2", "newName" => "newName2"]
]);

// Delete files
$uploadThing->deleteFiles(["fileKey1", "fileKey2"]);
```

## 📄 License

[MIT](LICENSE)