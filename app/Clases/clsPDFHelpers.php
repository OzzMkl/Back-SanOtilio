<?php

namespace App\Clases;

use TCPDF;


class clsPDFHelpers
{
    /**
     * Cabecera para PDF
     */
    public static function addHeader(TCPDF $pdf, $Empresa){
        $pdf->SetFont('helvetica', '', 18); // Establece la fuente
        // Buscamos imagen y la decodificamos 
        $file = base64_encode(\Storage::disk('images')->get('logo-solo2.png'));
        // descodificamos y asignamos
        $image = base64_decode($file);
        // insertamos imagen se pone @ para especificar que es a base64
        // imagen, x1, y1, ancho, largo
        $pdf->Image('@'.$image, 10, 9, 25, 25);
        $pdf->setXY(40, 8);
        // ESCRIBIMOS
        // ancho, altura, texto, borde, salto de línea
        $pdf->Cell(0, 10, $Empresa->nombreLargo, 0, 1); // Agrega un texto

        $pdf->SetFont('helvetica', '', 9); // Establece la fuente
        $pdf->setXY(45, 15);
        $pdf->Cell(0, 10, $Empresa->nombreCorto.': COLONIA '.$Empresa->colonia.', CALLE '.$Empresa->calle.' #'.$Empresa->numero.', '.$Empresa->ciudad.', '.$Empresa->estado, 0, 1); // Agrega un texto

        $pdf->setXY(60, 20);
        $pdf->Cell(0, 10, 'CORREOS: '.$Empresa->correo1.', '.$Empresa->correo2);

        $pdf->setXY(68, 25);
        $pdf->Cell(0, 10, 'TELEFONOS: '.$Empresa->telefono.' ó '.$Empresa->telefono2.'   RFC: '.$Empresa->rfc);

        $pdf->SetDrawColor(255, 145, 0); // insertamos color a pintar en RGB
        $pdf->SetLineWidth(2.5); // grosor de la línea
        $pdf->Line(10, 37, 200, 37); // X1, Y1, X2, Y2

        $pdf->SetLineWidth(5); // grosor de la línea
        $pdf->Line(10, 43, 58, 43); // X1, Y1, X2, Y2
    }

    public static function addPDFTableVentas(TCPDF $pdf, $productosVenta){
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(.2);
        $pdf->SetFillColor(7, 149, 223);
        $pdf->setXY(10, 78);

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(32, 10, 'CLAVE EXTERNA', 1, 0, 'C', true);
        $pdf->Cell(75, 10, 'DESCRIPCION', 1, 0, 'C', true);
        $pdf->Cell(16, 10, 'MEDIDA', 1, 0, 'C', true);
        $pdf->Cell(12, 10, 'CANT.', 1, 0, 'C', true);
        $pdf->Cell(18, 10, 'PRECIO', 1, 0, 'C', true);
        $pdf->Cell(16, 10, 'DESC.', 1, 0, 'C', true);
        $pdf->Cell(20, 10, 'SUBTOTAL', 1, 0, 'C', true);
        $pdf->Ln();

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);

        foreach ($productosVenta as $prodC) {
            
            if ($pdf->getY() > 270) {
                $pdf->AddPage();
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(32, 10, 'CLAVE EXTERNA', 1, 0, 'C', true);
                $pdf->Cell(75, 10, 'DESCRIPCION', 1, 0, 'C', true);
                $pdf->Cell(16, 10, 'MEDIDA', 1, 0, 'C', true);
                $pdf->Cell(12, 10, 'CANT.', 1, 0, 'C', true);
                $pdf->Cell(18, 10, 'PRECIO', 1, 0, 'C', true);
                $pdf->Cell(16, 10, 'DESC.', 1, 0, 'C', true);
                $pdf->Cell(20, 10, 'SUBTOTAL', 1, 0, 'C', true);
                $pdf->Ln();
            }

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            //Calculamos la alutra de la celda
            $hCell = ceil(strlen($prodC->descripcion) / 45) * 5;
            //Limitamos la altura a solo 25
            $hCell = $hCell > 25 ? 25 : $hCell;
            $pdf->MultiCell(32, $hCell, $prodC->claveEx, 1, 'C', false, 0);
            $pdf->MultiCell(75, $hCell, $prodC->descripcion, 1, 'C', false, 0);
            $pdf->MultiCell(16, $hCell, $prodC->nombreMedida, 1, 'C', false, 0);
            $pdf->MultiCell(12, $hCell, $prodC->cantidad, 1, 'C', false, 0);
            $pdf->MultiCell(18, $hCell, '$'.number_format($prodC->precio, 2), 1, 'C', false, 0);
            $pdf->MultiCell(16, $hCell, '$'.number_format($prodC->descuento, 2), 1, 'C', false, 0);
            $pdf->MultiCell(20, $hCell, '$'.number_format($prodC->subtotal, 2), 1, 'C', false, 0);
            $pdf->Ln();

        }
    }
}