<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Repository\MeasurementRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Measurement;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use  App\Form\MeasurementFormType;

class MeasurementController extends AbstractController
{
    private $em;
    private $measurementRepository;
    public function __construct(EntityManagerInterface $em, MeasurementRepository $measurementRepository) 
    {
        $this->em = $em;
        $this->measurementRepository = $measurementRepository;
    }

    #[Route('/measurement', name: 'app_measurement')]
    public function index(): Response
    {
        $form = $this->createForm(MeasurementFormType::class);
        return $this->render('measurement/index.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/upload/excel', name: 'upload_excel')]
    public function xslx(Request $request): Response
    { 
        $form = $this->createForm(MeasurementFormType::class);
        $form->handleRequest($request);

        $file = $form->get('file')->getData();
        $fileFolder = __DIR__ . '/../../data/uploads/'; 
        
        $filePathName = md5(uniqid()) . $file->getClientOriginalName(); 
        if ($file->getClientOriginalExtension()!='xlsx') {
            $this->addFlash('fail', 'Please upload the xlsx file only');
            return $this->redirectToRoute('app_measurement');
        }
        try {
            $file->move($fileFolder, $filePathName);
        } catch (FileException $e) {
            dd($e);
        }
        $spreadsheet = IOFactory::load($fileFolder . $filePathName); // Here we are able to read from the excel file 
        $row = $spreadsheet->getActiveSheet()->removeRow(1); // I added this to be able to remove the first file line 
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true); // here, the read data is turned into an array
        foreach ($sheetData as $Row) 
            { 
                $region_name = $Row['A']; 
                $site_name= $Row['B'];
                $pollutant = $Row['C'];
                $measurement = $Row['D'];
                $date = $Row['E'];
                $time = $Row['F'];

                $pollutant_data = new Measurement(); 
                $pollutant_data->setRegionName($region_name);           
                $pollutant_data->setSiteName($site_name);
                $pollutant_data->setPollutant($pollutant);
                $pollutant_data->setMeasurement($measurement);
                $pollutant_data->setDate($date);
                $pollutant_data->setTime($time);
                $this->em->persist($pollutant_data); 
                $this->em->flush(); 
            } 
        $this->addFlash('success', 'Data Uploaded Successfully');
        return $this->redirectToRoute('app_measurement');
    }

    #[Route('/export-csv', name: 'export_csv')]
    public function exportCsv(): Response
    {
        $spreadsheet = new Spreadsheet();
        $Excel_writer = new Csv($spreadsheet);
        
        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();
        
        $activeSheet->setCellValue('A1', 'Region Name');
        $activeSheet->setCellValue('B1', 'Pollutant');
        $activeSheet->setCellValue('C1', 'Measurement');

        $totalMeasurement = $this->measurementRepository->aggregatedTotalMeasurement();
        $count=2;
        foreach($totalMeasurement as $key=>$item) {
            $activeSheet->setCellValue('A'.$count, $item['regionName']);
            $activeSheet->setCellValue('B'.$count, $item['pollutant']);
            $activeSheet->setCellValue('C'.$count, $item['measurement']);
            $count++;
        }
        $filename = 'products.csv';
        header('Content-Type: application/text-csv');
        header('Content-Disposition: attachment;filename='. $filename);
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');exit();
    }
}
