<?php
namespace App\Common;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
class QrcodeCommon {


    public static function generateQrCodeBase64($data)
    {
        // 使用Builder构建二维码
        $builder = new \Builder(
            writer: new PngWriter(),  // 使用 PngWriter 写入二维码
            writerOptions: [],  // Writer 的选项
            validateResult: false,  // 不验证生成结果
            data: $data,  // 要编码的数据
            encoding: new Encoding('UTF-8'),  // 编码方式
            errorCorrectionLevel: ErrorCorrectionLevel::High,  // 错误修正级别
            size: 300,  // 二维码大小
            margin: 10,  // 外边距
            roundBlockSizeMode: RoundBlockSizeMode::Margin,  // 块的圆角样式
            logoPath: '',  // logo路径（如果有）
            logoResizeToWidth: 50,  // logo宽度
            logoPunchoutBackground: true,  // logo透明背景
            labelText: 'This is the label',  // 标签文本
            labelFont: new OpenSans(20),  // 标签字体
            labelAlignment: LabelAlignment::Center  // 标签对齐方式
        );

        // 生成二维码
        $result = $builder->build();

        // 将二维码图片转为Base64编码
        $qrCodeImage = $result->getString();
        $base64Image = base64_encode($qrCodeImage);

        // 返回Base64格式的二维码
        return 'data:image/png;base64,' . $base64Image;
    }

}




