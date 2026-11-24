<?php

namespace App\Core;

// Arma el Excel (.xlsx) de asistencias con el diseño de RutaSegura, sin librerías externas.
// Un .xlsx es un ZIP con archivos XML; aquí se escriben las dos cosas (no necesita ext-zip).
class ExcelAsistencia
{
    private const FILA_TITULOS = 10;

    // Números de estilo: posición de cada <xf> en estilos()
    private const FONDO = 1, TITULO = 2, MARCA = 3, SUBTITULO = 4, COLEGIO = 5;
    private const FRANJAS = [6, 7, 8, 9];
    private const ROTULO_IDA = 10, ROTULO_REGRESO = 11, ROTULO_ALERTA = 12;
    private const CIFRA_IDA = 13, CIFRA_REGRESO = 14, CIFRA_ALERTA = 15;
    private const ENCABEZADO = 16, NOMBRE = 17, SUAVE = 18, TEXTO = 19;
    private const ALERTA = 20, GUION = 21, SIN_REGISTRO = 22, HORA_IDA = 23, QUIEN = 24, PIE = 25;
    private const HORA_REGRESO = 26;

    private array $celdas = [];
    private array $altos = [];

    // Devuelve el contenido binario del .xlsx. Con $todas agrega la columna RUTA.
    public function crear(array $estudiantes, string $nombreRuta, array $auxiliares, string $fecha, bool $todas): string
    {
        // Columna => ancho (la A y la última son márgenes)
        $columnas = ['ESTUDIANTE' => 30, 'CURSO' => 13] + ($todas ? ['RUTA' => 26] : [])
            + ['PARADA' => 24, 'IDA' => 13, 'REGRESO' => 14, 'REGISTRÓ' => 38];
        $letra = [];
        foreach (array_keys($columnas) as $i => $titulo) {
            $letra[$titulo] = chr(ord('B') + $i);
        }
        $ultima = $letra['REGISTRÓ'];
        $margen = chr(ord($ultima) + 1);

        // ----- Cabecera -----
        $fechaTexto = date('d/m/Y', strtotime($fecha));
        $this->altos = [1 => 12, 2 => 34, 3 => 20, 4 => 18, 5 => 4, 6 => 14, 7 => 22, 8 => 32, 9 => 16, self::FILA_TITULOS => 30];
        $this->texto('B2', 'Asistencias', self::TITULO);
        $this->texto($ultima . '2', 'RutaSegura', self::MARCA);
        $this->texto('B3', $nombreRuta . ' · ' . $fechaTexto, self::SUBTITULO);
        $this->texto($ultima . '3', 'I.E. N° 4 San José Campestre', self::COLEGIO);
        $this->texto('B4', (count($auxiliares) > 1 ? 'Auxiliares: ' : 'Auxiliar: ') . ($auxiliares ? implode(', ', $auxiliares) : 'Sin asignar'), self::SUBTITULO);

        // Franja con los cuatro colores del colegio
        $total = count($columnas);
        foreach (array_values($letra) as $i => $col) {
            $this->celdas[5][$col] = [self::FRANJAS[min(3, intdiv($i * 4, $total))], ''];
        }

        // ----- Tabla -----
        foreach ($letra as $titulo => $col) {
            $this->texto($col . self::FILA_TITULOS, $titulo, self::ENCABEZADO);
        }

        $fila = self::FILA_TITULOS;
        foreach ($estudiantes as $e) {
            $fila++;
            $this->altos[$fila] = 32;
            $this->texto($letra['ESTUDIANTE'] . $fila, $e['estudiante'], self::NOMBRE);
            $this->texto($letra['CURSO'] . $fila, $e['curso'], self::SUAVE);
            if ($todas) {
                $this->texto($letra['RUTA'] . $fila, $e['ruta'], self::TEXTO);
            }
            $this->texto($letra['PARADA'] . $fila, $e['parada'], self::TEXTO);

            $e['hora_ida'] !== null
                ? $this->hora($letra['IDA'] . $fila, $e['hora_ida'], self::HORA_IDA)
                : $this->texto($letra['IDA'] . $fila, 'No subió', self::ALERTA);

            if ($e['hora_regreso'] !== null) {
                $this->hora($letra['REGRESO'] . $fila, $e['hora_regreso'], self::HORA_REGRESO);
            } elseif ($e['hora_ida'] !== null) {
                $this->texto($letra['REGRESO'] . $fila, 'Pendiente', self::ALERTA);
            } else {
                $this->texto($letra['REGRESO'] . $fila, '—', self::GUION);
            }

            $quien = $this->quienRegistro($e);
            $this->texto($ultima . $fila, $quien ?? 'Sin registro', $quien ? self::QUIEN : self::SIN_REGISTRO);
        }

        // ----- Resumen: con fórmulas, para que cambie si se corrige la tabla -----
        $desde = self::FILA_TITULOS + 1;
        $hasta = max($fila, $desde);
        $rango = fn(string $titulo): string => $letra[$titulo] . $desde . ':' . $letra[$titulo] . $hasta;
        $n = count($estudiantes);
        $ida = count(array_filter($estudiantes, fn($e) => $e['hora_ida'] !== null));
        $regreso = count(array_filter($estudiantes, fn($e) => $e['hora_regreso'] !== null));

        $this->texto('B7', 'SUBIERON · IDA', self::ROTULO_IDA);
        $this->formula('B8', 'COUNT(' . $rango('IDA') . ')&" de "&COUNTA(' . $rango('ESTUDIANTE') . ')', "$ida de $n", self::CIFRA_IDA);
        $this->texto($letra['PARADA'] . '7', 'BAJARON · REGRESO', self::ROTULO_REGRESO);
        $this->formula($letra['PARADA'] . '8', 'COUNT(' . $rango('REGRESO') . ')&" de "&COUNT(' . $rango('IDA') . ')', "$regreso de $ida", self::CIFRA_REGRESO);
        $this->texto($ultima . '7', 'NO SUBIERON', self::ROTULO_ALERTA);
        $this->formula($ultima . '8', 'COUNTIF(' . $rango('IDA') . ',"No subió")', $n - $ida, self::CIFRA_ALERTA);

        if ($n === 0) {
            $this->texto('B' . $desde, '', self::FONDO);
            $this->texto('B' . ($desde + 1), 'No hay estudiantes en esta ruta.', self::SUBTITULO);
        }
        $filaPie = $hasta + 2 + ($n === 0 ? 1 : 0);
        $this->texto('B' . $filaPie, 'Generado por RutaSegura · ' . date('d/m/Y H:i'), self::PIE);

        return $this->zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>',
            '_rels/.rels' => $this->relaciones(['officeDocument' => 'xl/workbook.xml']),
            'xl/workbook.xml' => $this->libro($ultima, $margen, $hasta, $filaPie + 1),
            'xl/_rels/workbook.xml.rels' => $this->relaciones(['worksheet' => 'worksheets/sheet1.xml', 'styles' => 'styles.xml']),
            'xl/worksheets/sheet1.xml' => $this->hoja($columnas, $letra, $margen, $ultima, $hasta, max(40, $filaPie + 10)),
            'xl/worksheets/_rels/sheet1.xml.rels' => $this->relaciones(['drawing' => '../drawings/drawing1.xml']),
            'xl/styles.xml' => $this->estilos(),
            'xl/drawings/drawing1.xml' => $this->dibujo(),
            'xl/drawings/_rels/drawing1.xml.rels' => $this->relaciones(['image' => '../media/image1.png']),
            'xl/media/image1.png' => (string) file_get_contents(__DIR__ . '/../../public/img/marca-excel.png'),
        ]);
    }

    // "Auxiliar · Nombre", "Manual · Administración" o null si no hay registros
    private function quienRegistro(array $e): ?string
    {
        $partes = [];
        foreach (['ida', 'regreso'] as $tipo) {
            if ($e['usuario_' . $tipo] !== null) {
                $partes[] = $e['es_admin_' . $tipo] ? 'Manual · Administración' : 'Auxiliar · ' . $e['usuario_' . $tipo];
            }
        }
        return $partes ? implode(' / ', array_unique($partes)) : null;
    }

    // ----- Celdas -----

    private function xml($texto): string
    {
        // Sin caracteres de control: dejarían el archivo dañado
        $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $texto) ?? '';
        return htmlspecialchars($limpio, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function poner(string $ref, int $estilo, string $contenido): void
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $partes);
        $this->celdas[(int) $partes[2]][$partes[1]] = [$estilo, $contenido];
    }

    // Como texto en línea: aunque empiece por "=" Excel nunca lo ejecuta
    private function texto(string $ref, $texto, int $estilo): void
    {
        $this->poner($ref, $estilo, $texto === '' ? '' : ' t="inlineStr"><is><t>' . $this->xml($texto) . '</t></is>');
    }

    // Hora real de Excel (fracción del día), para poder ordenar y calcular
    private function hora(string $ref, string $hora, int $estilo): void
    {
        [$h, $m] = array_map('intval', explode(':', $hora));
        $this->poner($ref, $estilo, '><v>' . number_format(($h * 60 + $m) / 1440, 10, '.', '') . '</v>');
    }

    private function formula(string $ref, string $formula, $valor, int $estilo): void
    {
        $this->poner($ref, $estilo, (is_string($valor) ? ' t="str"' : '') . '><f>' . $this->xml($formula) . '</f><v>' . $this->xml($valor) . '</v>');
    }

    // ----- Partes del archivo -----

    private function hoja(array $columnas, array $letra, string $margen, string $ultima, int $hasta, int $ultimaFila): string
    {
        $primera = self::FILA_TITULOS + 1;
        $cols = '<col min="1" max="1" width="3" customWidth="1"/>';
        $i = 1;
        foreach ($columnas as $ancho) {
            $i++;
            $cols .= '<col min="' . $i . '" max="' . $i . '" width="' . $ancho . '" customWidth="1"/>';
        }
        // Desde el margen derecho en adelante, fondo verde
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="3" customWidth="1"/>'
            . '<col min="' . ($i + 2) . '" max="' . ($i + 7) . '" width="10" customWidth="1"/>';
        $todasLasLetras = array_map('chr', range(ord('A'), ord('A') + $i + 6));

        $filas = '';
        for ($f = 1; $f <= $ultimaFila; $f++) {
            $filas .= '<row r="' . $f . '"' . (isset($this->altos[$f]) ? ' ht="' . $this->altos[$f] . '" customHeight="1"' : '') . '>';
            foreach ($todasLasLetras as $col) {
                [$estilo, $contenido] = $this->celdas[$f][$col] ?? [self::FONDO, ''];
                $filas .= '<c r="' . $col . $f . '" s="' . $estilo . '"' . ($contenido === '' ? '/>' : $contenido . '</c>');
            }
            $filas .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><tabColor rgb="FF1E8C3A"/><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="' . self::FILA_TITULOS . '" topLeftCell="A' . $primera . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="B' . $primera . '" sqref="B' . $primera . '"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><cols>' . $cols . '</cols><sheetData>' . $filas . '</sheetData>'
            . '<autoFilter ref="B' . self::FILA_TITULOS . ':' . $ultima . $hasta . '"/>'
            . '<pageMargins left="0.4" right="0.4" top="0.4" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '<headerFooter><oddFooter>&amp;CPágina &amp;P de &amp;N</oddFooter></headerFooter>'
            . '<drawing r:id="rId1"/></worksheet>';
    }

    private function libro(string $ultima, string $margen, int $hasta, int $filaFinal): string
    {
        $t = self::FILA_TITULOS;
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Asistencias" sheetId="1" r:id="rId1"/></sheets><definedNames>'
            . '<definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">Asistencias!$B$' . $t . ':$' . $ultima . '$' . $hasta . '</definedName>'
            . '<definedName name="_xlnm.Print_Area" localSheetId="0">Asistencias!$A$1:$' . $margen . '$' . $filaFinal . '</definedName>'
            . '<definedName name="_xlnm.Print_Titles" localSheetId="0">Asistencias!$' . $t . ':$' . $t . '</definedName>'
            . '</definedNames><calcPr fullCalcOnLoad="1"/></workbook>';
    }

    private function relaciones(array $destinos): string
    {
        $xml = '';
        $n = 0;
        foreach ($destinos as $tipo => $destino) {
            $xml .= '<Relationship Id="rId' . ++$n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $tipo . '" Target="' . $destino . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $xml . '</Relationships>';
    }

    // Marca de agua del escudo sobre la tabla
    private function dibujo(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<xdr:oneCellAnchor><xdr:from><xdr:col>2</xdr:col><xdr:colOff>561975</xdr:colOff><xdr:row>5</xdr:row><xdr:rowOff>95250</xdr:rowOff></xdr:from><xdr:ext cx="3600450" cy="4191000"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Escudo" descr="Escudo del colegio"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="3600450" cy="4191000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }

    private function estilos(): string
    {
        $fuente = fn(int $tam, string $color, string $extra = ''): string => '<font>' . $extra . '<sz val="' . $tam . '"/><color rgb="FF' . $color . '"/><name val="Arial"/></font>';
        $fuentes = [
            '<font><sz val="11"/><name val="Arial"/></font>',  // 0
            $fuente(22, 'FBFBF4', '<b/>'),  // 1 título
            $fuente(11, 'B9CDBF'),          // 2 subtítulo
            $fuente(14, 'F4D21F', '<b/>'),  // 3 marca
            $fuente(10, 'B9CDBF'),          // 4 suave
            $fuente(9, 'B9CDBF', '<b/>'),   // 5 rótulo
            $fuente(18, 'F4D21F', '<b/>'),  // 6 cifra ida
            $fuente(18, '9DB4FF', '<b/>'),  // 7 cifra regreso
            $fuente(18, 'FF8A6B', '<b/>'),  // 8 cifra alerta
            $fuente(11, 'FBFBF4', '<b/>'),  // 9 nombre
            $fuente(10, 'FBFBF4'),          // 10 texto
            $fuente(11, 'FF8A6B', '<b/>'),  // 11 alerta
            $fuente(11, '86A893', '<b/>'),  // 12 guion
            $fuente(10, '86A893', '<i/>'),  // 13 sin registro
            $fuente(11, 'F4D21F', '<b/>'),  // 14 hora ida
            $fuente(10, 'B9CDBF', '<b/>'),  // 15 quién
            $fuente(9, '86A893', '<i/>'),   // 16 pie
            $fuente(11, '9DB4FF', '<b/>'),  // 17 hora regreso
        ];
        $rellenos = '<fill><patternFill/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach (['0B3A20', '1E8C3A', 'F4D21F', 'E84C2B', '1F3A8C', '114A2A', '072A16'] as $color) {
            $rellenos .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $color . '"/></patternFill></fill>';
        }
        $bordes = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        foreach (['F4D21F', '9DB4FF', 'FF8A6B'] as $color) {
            $bordes .= '<border><left style="thick"><color rgb="FF' . $color . '"/></left><right/><top/><bottom/><diagonal/></border>';
        }
        foreach (['medium' => 'F4D21F', 'thin' => '2E6B45'] as $grosor => $color) {
            $bordes .= '<border><left/><right/><top/><bottom style="' . $grosor . '"><color rgb="FF' . $color . '"/></bottom><diagonal/></border>';
        }

        // [fuente, relleno, borde, alineación horizontal, vertical, sangría, formato]
        $izq = fn(int $f, int $r, int $b, int $sangria = 0, string $v = 'center', int $num = 0) => [$f, $r, $b, 'left', $v, $sangria, $num];
        $formatos = [
            $izq(0, 0, 0), $izq(0, 2, 0), $izq(1, 2, 0), [3, 2, 0, 'right', 'center', 0, 0], $izq(2, 2, 0), [4, 2, 0, 'right', 'center', 0, 0],
            $izq(0, 3, 0), $izq(0, 4, 0), $izq(0, 5, 0), $izq(0, 6, 0),
            $izq(5, 7, 1, 1, 'bottom'), $izq(5, 7, 2, 1, 'bottom'), $izq(5, 7, 3, 1, 'bottom'),
            $izq(6, 7, 1, 1), $izq(7, 7, 2, 1), $izq(8, 7, 3, 1),
            $izq(5, 8, 4, 1), $izq(9, 2, 5, 1), $izq(4, 2, 5, 1), $izq(10, 2, 5, 1),
            $izq(11, 2, 5, 1), $izq(12, 2, 5, 1), $izq(13, 2, 5, 1), $izq(14, 2, 5, 1, 'center', 164), $izq(15, 2, 5, 1), $izq(16, 2, 0),
            $izq(17, 2, 5, 1, 'center', 164),
        ];
        $xfs = '';
        foreach ($formatos as [$f, $r, $b, $h, $v, $sangria, $num]) {
            $xfs .= '<xf numFmtId="' . $num . '" fontId="' . $f . '" fillId="' . $r . '" borderId="' . $b . '" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="' . $h . '" vertical="' . $v . '"' . ($sangria ? ' indent="' . $sangria . '"' : '') . '/></xf>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="hh:mm"/></numFmts>'
            . '<fonts count="' . count($fuentes) . '">' . implode('', $fuentes) . '</fonts><fills count="9">' . $rellenos . '</fills><borders count="6">' . $bordes . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($formatos) . '">' . $xfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    // ZIP sin compresión: cabecera + datos por archivo, y al final el directorio
    private function zip(array $archivos): string
    {
        $cuerpo = '';
        $directorio = '';
        $partes = getdate();
        $hora = ($partes['hours'] << 11) | ($partes['minutes'] << 5) | intdiv($partes['seconds'], 2);
        $dia = (($partes['year'] - 1980) << 9) | ($partes['mon'] << 5) | $partes['mday'];

        foreach ($archivos as $nombre => $datos) {
            $comun = pack('vvvvvVVVvv', 20, 0x0800, 0, $hora, $dia, crc32($datos), strlen($datos), strlen($datos), strlen($nombre), 0);
            $directorio .= "PK\x01\x02" . pack('v', 20) . $comun . pack('vvvVV', 0, 0, 0, 0, strlen($cuerpo)) . $nombre;
            $cuerpo .= "PK\x03\x04" . $comun . $nombre . $datos;
        }

        return $cuerpo . $directorio
            . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($archivos), count($archivos), strlen($directorio), strlen($cuerpo), 0);
    }
}
