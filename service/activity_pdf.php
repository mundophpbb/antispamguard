<?php
/**
 * One-page PDF export of the ACP activity chart.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\service;

class activity_pdf
{
	public function render($title, $period, $note, array $headings, array $rows, array $totals)
	{
		$page = "0.10 0.18 0.26 rg\n";
		$page .= $this->text(45, 790, 22, $title);
		$page .= $this->text(45, 767, 10, $period);
		$page .= $this->text(45, 749, 9, $note);

		$page .= "0.87 0.92 0.95 rg 45 707 505 26 re f\n0.10 0.18 0.26 rg\n";
		$columns = array(55, 230, 400);
		foreach ($headings as $index => $heading)
		{
			$page .= $this->text($columns[$index], 717, 10, $heading);
		}

		$y = 694;
		foreach ($rows as $row)
		{
			$page .= $this->table_row($columns, $y, $row);
			$y -= 19;
		}
		$page .= "0.77 0.82 0.86 RG 45 " . ($y + 12) . " m 550 " . ($y + 12) . " l S\n";
		$page .= $this->table_row($columns, $y - 2, $totals);

		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
			'<< /Length ' . strlen($page) . " >>\nstream\n" . $page . "endstream",
		);

		$pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
		$offsets = '';
		foreach ($objects as $index => $object)
		{
			$offsets .= sprintf("%010d 00000 n \n", strlen($pdf));
			$pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen($pdf);
		return $pdf . "xref\n0 6\n0000000000 65535 f \n" . $offsets
			. "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	}

	protected function table_row(array $columns, $y, array $row)
	{
		$output = '';
		foreach ($row as $index => $value)
		{
			$output .= $this->text($columns[$index], $y, 10, $value);
		}
		return $output;
	}

	protected function text($x, $y, $size, $value)
	{
		$value = (string) $value;
		$encoded = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) : false;
		if ($encoded === false)
		{
			$encoded = function_exists('mb_convert_encoding')
				? mb_convert_encoding($value, 'Windows-1252', 'UTF-8') : preg_replace('/[^ -~]/u', '?', $value);
		}
		// Hex strings carry literal WinAnsi bytes without PDF escape sequences.
		return 'BT /F1 ' . (int) $size . ' Tf 1 0 0 1 ' . (int) $x . ' ' . (int) $y
			. ' Tm <' . bin2hex($encoded) . "> Tj ET\n";
	}
}
