<?php
namespace App\Common;


use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;
class QrcodeCommon {

    /**
     * 生成二维码图片转成base64
     * @param string $info 内容
     * @return string
     */
    public static function generateQrCodeBase64(string $info)
    {
        $qrCode = QrCode::create($info)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
            ->setSize(300)
            ->setMargin(10)
            ->setForegroundColor(new Color(...[0, 0, 0]))
            ->setBackgroundColor(new Color(255, 255, 255));
        // 生成 PNG 格式的 Base64
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return 'data:image/png;base64,' . base64_encode($result->getString());
    }

}




