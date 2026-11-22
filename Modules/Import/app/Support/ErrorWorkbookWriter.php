<?php

namespace Modules\Import\Support;

use Illuminate\Support\Facades\Storage;
use Modules\Import\Models\DataImport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;
use ZipArchive;

class ErrorWorkbookWriter
{
    /**
     * Stream both worksheets to temporary files to bound memory use for large imports.
     */
    public function write(DataImport $import): string
    {
        $sheet = tempnam(sys_get_temp_dir(), 'import-sheet-');
        $errors = tempnam(sys_get_temp_dir(), 'import-errors-');
        $archive = tempnam(sys_get_temp_dir(), 'import-xlsx-');
        $comments = tempnam(sys_get_temp_dir(), 'import-comments-');
        $drawing = tempnam(sys_get_temp_dir(), 'import-notes-');
        $sheetHandle = fopen($sheet, 'wb');
        $errorHandle = fopen($errors, 'wb');
        $commentHandle = fopen($comments, 'wb');
        $drawingHandle = fopen($drawing, 'wb');

        try {
            fwrite($commentHandle, '<?xml version="1.0" encoding="UTF-8"?>'
                .'<comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<authors><author>Forkiva</author></authors><commentList>');
            fwrite($drawingHandle, '<xml xmlns:v="urn:schemas-microsoft-com:vml"'
                .' xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
                .'<o:shapelayout v:ext="edit"><o:idmap v:ext="edit" data="1"/></o:shapelayout>'
                .'<v:shapetype id="_x0000_t202" coordsize="21600,21600" o:spt="202" path="m,l,21600r21600,l21600,xe">'
                .'<v:stroke joinstyle="miter"/><v:path gradientshapeok="t" o:connecttype="rect"/></v:shapetype>');
            $noteId = 1024;
            fwrite($sheetHandle, $this->sheetStart());
            fwrite($errorHandle, $this->sheetStart());
            $this->writeRow($sheetHandle, 1, $import->headers);
            $this->writeRow($errorHandle, 1, [
                __('import::imports.workbook.row'),
                __('import::imports.workbook.column'),
                __('import::imports.workbook.error'),
            ]);
            $errorLine = 2;

            $import->rows()
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($import, $sheetHandle, $errorHandle, $commentHandle, $drawingHandle, &$errorLine, &$noteId): void {
                    foreach ($rows as $row) {
                        $values = $row->values;
                        $highlight = [];
                        $notes = [];
                        foreach ($row->errors ?? [] as $field => $messages) {
                            $column = $import->mapping[$field] ?? null;
                            if ($column !== null) {
                                $highlight[(int) $column] = true;
                            } else {
                                $highlight = array_fill_keys(array_keys($import->headers), true);
                            }
                            $noteColumns = $column === null ? array_keys($import->headers) : [(int) $column];
                            foreach ($noteColumns as $noteColumn) {
                                $notes[$noteColumn] = array_merge($notes[$noteColumn] ?? [], (array) $messages);
                            }
                            foreach ((array) $messages as $message) {
                                $this->writeRow($errorHandle, $errorLine++, [
                                    $row->row_number,
                                    $column === null ? __('import::imports.workbook.row') : $import->headers[$column],
                                    $message,
                                ]);
                            }
                        }
                        if (isset($row->errors['_row'])) {
                            $highlight = array_fill_keys(array_keys($import->headers), true);
                        }
                        $this->writeRow($sheetHandle, $row->row_number, $values, $highlight);
                        foreach ($notes as $column => $messages) {
                            $this->writeNote($commentHandle, $drawingHandle, ++$noteId, $row->row_number, $column, $messages);
                        }
                    }
                });

            fwrite($sheetHandle, '</sheetData><legacyDrawing r:id="rIdNotes"/></worksheet>');
            fwrite($errorHandle, '</sheetData></worksheet>');
            fwrite($commentHandle, '</commentList></comments>');
            fwrite($drawingHandle, '</xml>');
            fclose($sheetHandle);
            fclose($errorHandle);
            fclose($commentHandle);
            fclose($drawingHandle);
            $this->archive($archive, $sheet, $errors, $comments, $drawing);

            $directory = trim((string) config('import.path', 'imports'), '/')
                .'/'.$import->created_by.'/'.$import->id;
            $path = $directory.'/validation-errors.xlsx';
            $stream = fopen($archive, 'rb');
            try {
                if (! Storage::disk($import->disk)->put($path, $stream)) {
                    throw new RuntimeException('Could not store the validation workbook.');
                }
            } finally {
                fclose($stream);
            }

            return $path;
        } finally {
            if (is_resource($sheetHandle)) {
                fclose($sheetHandle);
            }
            if (is_resource($errorHandle)) {
                fclose($errorHandle);
            }
            if (is_resource($commentHandle)) {
                fclose($commentHandle);
            }
            if (is_resource($drawingHandle)) {
                fclose($drawingHandle);
            }
            @unlink($comments);
            @unlink($drawing);
            @unlink($sheet);
            @unlink($errors);
            @unlink($archive);
        }
    }

    private function sheetStart(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetData>';
    }

    /**
     * Use inline strings so distinct cell values do not build a shared-string table.
     *
     * @param  resource  $handle
     */
    private function writeRow($handle, int $number, array $values, array $highlight = []): void
    {
        fwrite($handle, '<row r="'.$number.'">');
        foreach ($values as $index => $value) {
            $coordinate = Coordinate::stringFromColumnIndex((int) $index + 1).$number;
            $style = isset($highlight[$index]) ? ' s="1"' : '';
            $text = $this->escape((string) ($value ?? ''));
            fwrite(
                $handle,
                '<c r="'.$coordinate.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'
                    .$text.'</t></is></c>',
            );
        }
        fwrite($handle, '</row>');
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Pair a cell comment with a hidden legacy note shape so Excel displays it on hover.
     *
     * @param  resource  $comments
     * @param  resource  $drawing
     */
    private function writeNote($comments, $drawing, int $id, int $row, int $column, array $messages): void
    {
        $coordinate = Coordinate::stringFromColumnIndex($column + 1).$row;
        $message = $this->escape(implode("\n", array_unique($messages)));
        fwrite($comments, '<comment ref="'.$coordinate.'" authorId="0"><text><t xml:space="preserve">'
            .$message.'</t></text></comment>');
        fwrite($drawing, '<v:shape id="_x0000_s'.$id.'" type="#_x0000_t202"'
            .' style="position:absolute;margin-left:80pt;margin-top:5pt;width:240pt;height:90pt;z-index:1;visibility:hidden"'
            .' fillcolor="#ffffe1" o:insetmode="auto"><v:fill color2="#ffffe1"/>'
            .'<v:shadow on="t" color="black" obscured="t"/><v:path o:connecttype="none"/>'
            .'<v:textbox style="mso-direction-alt:auto"><div style="text-align:left"/></v:textbox>'
            .'<x:ClientData ObjectType="Note"><x:MoveWithCells/><x:SizeWithCells/><x:AutoFill>False</x:AutoFill>'
            .'<x:Row>'.($row - 1).'</x:Row><x:Column>'.$column.'</x:Column></x:ClientData></v:shape>');
    }

    private function archive(string $path, string $sheet, string $errors, string $comments, string $drawing): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the validation workbook.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="vml" ContentType="application/vnd.openxmlformats-officedocument.vmlDrawing"/>'
            .'<Override PartName="/xl/comments1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $originalSheet = $this->escape(__('import::imports.workbook.original_data'));
        $errorsSheet = $this->escape(__('import::imports.workbook.validation_errors'));
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$originalSheet.'" sheetId="1" r:id="rId1"/><sheet name="'.$errorsSheet.'" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFCDD2"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/></cellXfs>'
            .'</styleSheet>');
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments1.xml"/>'
            .'<Relationship Id="rIdNotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/vmlDrawing" Target="../drawings/notes1.vml"/>'
            .'</Relationships>');
        $zip->addFile($comments, 'xl/comments1.xml');
        $zip->addFile($drawing, 'xl/drawings/notes1.vml');
        $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
        $zip->addFile($errors, 'xl/worksheets/sheet2.xml');
        if (! $zip->close()) {
            throw new RuntimeException('Could not finish the validation workbook.');
        }
    }
}
