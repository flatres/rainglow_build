<?php

//settings array = [
//   'title' => default ''
//   'filename' => //or autogenerate
//   'path' => eg 'exams/gcse/' default: spreadsheets
//   'sheetTitle' => default Sheet1
//   'sheetColor',
     // 'timeStamp'  => default false
// ]

// $columns = [
//   [
//     'field' => 'name',
//     'label' =>  'Name'
//   ]
// ]

namespace Utilities\Spreadsheet; 

use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as Worksheet;

class SingleSheet
{
  public $url = '';
  public $filename = '';
  public $path;
  public $package = [];
  public $settings = [];

  public function __construct(array $columns, array $data, array $settings = [])
  {
    $this->spreadsheet = new Spreadsheet();
    $this->settings = $settings;

    //delete the default sheet
    $sheetIndex = $this->spreadsheet->getIndex($this->spreadsheet->getSheetByName('Worksheet'));
    $this->spreadsheet->removeSheetByIndex($sheetIndex);

    $title = $settings['title'] ?? '';

    //set metadata
    $this->spreadsheet->getProperties()
                      ->setCreator("ADA")
                      ->setLastModifiedBy("ADA")
                      ->setTitle($title);

    $sheetTitle = $settings['sheetTitle'] ?? 'Sheet 1';
    $color = $settings['sheetColor'] ?? null;
    $this->generateSheet($columns, $data, $sheetTitle, $color);
     

    //generate file path and save sheet

    $path = $settings['path'] ?? 'spreadsheets/';
    if (isset($settings['filename']) && isset($settings['timestamp'])) $settings['filename'] .= ' (' . date('d-m-y@H.i.s',time()) . ').xlsx';
    $filename = $settings['filename'] ?? uniqid() . '(' . date('d-m-y@H.i.s',time()) . ')' . '.xlsx';

    $filepath = FILESTORE_PATH . "$path$filename";
    $url = FILESTORE_URL . "$path$filename";

    $this->writer = new Xlsx($this->spreadsheet);
    $this->writer->save($filepath);

    $this->url = $url;
    $this->filename = $filename;
    $this->path = $filepath;

    $this->package = [
      'file' => $filename,
      'url'  => $url
    ];

    return $this;
  }

  private function generateSheet($columns, $data, $title, $color)
  {
    $spreadsheet = $this->spreadsheet;
    $worksheet = new Worksheet($spreadsheet, $title);
    if ($color) $worksheet->getTabColor()->setRGB($color);
    $spreadsheet->addSheet($worksheet, 0);

    //sheet title
    $sheet = $spreadsheet->getSheetByName($title);

    $sheetData = [];
    $header = [];
    $colCount = 0;
    foreach ($columns as $column){
      $isHidden = $column['hidden'] ?? false;
      if ($isHidden) continue;
      $header[] = $column['label'];
      $colCount++;
    }
    $sheetData[] = $header;
    $sheetData[] = [];
    // $sheetData[] = []; //black row for the filter buttons
    foreach ($data as $d) {
      $row = [];
      foreach ($columns as $column){
        $isHidden = $column['hidden'] ?? false;
        if ($isHidden) continue;
        $row[] = $d[$column['field']] ?? '';
      }
      $sheetData[] = $row;
    }

    $forceText = $this->settings['forceText'] ?? false;
    if ($forceText) {
      $sheet->getStyle('A1:AZ1000')
            ->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }

    $sheet->fromArray(
        $sheetData,  // The data to set
        NULL,        // Array values with this value will not be set
        'A1'         // Top left coordinate of the worksheet range where
    );

    $styleArray = [
      'font' => [
          'bold' => true
          // 'size' => 18
      ]
    ];
    $maxCol = columnLetter($colCount);
    $sheet->getStyle('A1:'.$maxCol.'1')->applyFromArray($styleArray);
    // $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ';


    foreach (range(0,$colCount - 1) as $col) {
      $column = $columns[$col];
      if (isset($column['width'])) {
        $width = $column['width'];
        $sheet->getColumnDimension(columnLetter($col + 1))->setWidth($width);
      } else {
        $sheet->getColumnDimension(columnLetter($col + 1))->setAutoSize(true);
      }
    }

    $maxRow = count($data)+2;
    $sheet->setAutoFilter('A2:' . $maxCol . $maxRow);

  }

}
