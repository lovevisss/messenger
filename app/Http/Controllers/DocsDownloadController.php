<?php

namespace App\Http\Controllers;

use App\Support\ApiDocumentation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

class DocsDownloadController extends Controller
{
    public function __invoke(string $channel, string $format): Response
    {
        $doc = ApiDocumentation::for($channel, request()->root().'/api/v1/notifications');
        $filename = 'message-api-'.$channel.'.'.$format;
        $html = view('docs.offline', ['doc' => $doc, 'format' => $format])->render();

        if ($format === 'html') {
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'sun-exta',
            'tempDir' => $tempDir,
            'margin_top' => 18,
            'margin_bottom' => 18,
        ]);
        $pdf->SetTitle($doc['title']);
        $pdf->SetAuthor('消息提醒平台');
        $pdf->SetHTMLFooter('<div style="text-align:right;color:#718078;font-size:9pt">{PAGENO} / {nbpg}</div>');
        $pdf->WriteHTML($html);

        return response($pdf->OutputBinaryData(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
