<?php
// global $path;
// require_once($path. "tools/vendor/autoload.php");
namespace Utilities\QRCode;

class Generator {
    private $options;
    private $path, $file, $data;

    public function __construct(string $data, string $path= null, string $file = null)
    {
        $this->path = $path ? $path : FILESTORE_PATH . "qrcodes/";
        $this->file = $file ? $file : uniqid() . '.png';
        $this->data = $data;
        $this->options = new \chillerlan\QRCode\QROptions([
            'version'      => 10,
            'outputType'   => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'     => \chillerlan\QRCode\QRCode::ECC_H,
            'scale'        => 5,
            'imageBase64'  => false,
            'imageTransparent' => false

        ]);
            return $this;
    }

    public function render() {
        $pathToFile = $this->path . $this->file;
        (new \chillerlan\QRCode\QRCode($this->options))->render($this->data, $pathToFile);
        return $pathToFile;
    }

}
