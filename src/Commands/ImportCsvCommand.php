<?php
namespace Chillu\SingerGithubMulti\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Sminnee\StitchData\StitchApi;

class ImportCsvCommand extends Command
{
    
    /**
     * @var StitchApi
     */
    protected $api;
        
    protected function configure()
    {
        $this
            ->setName('import-csv')
            ->setDescription('Imports a Google Bigquery CSV export into Stitchdata')
            ->addArgument('table-name', InputArgument::REQUIRED, 'Stitchdata target table name')
            ->addArgument('file', InputArgument::REQUIRED, 'CSV file to import')
            ->addOption('client-id', null, InputOption::VALUE_REQUIRED, 'Stitchdata Client ID')
            ->addOption('client-token', null, InputOption::VALUE_REQUIRED, 'Stitchdata Client Token');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $tableName = $input->getArgument('table-name');
        $clientId = $input->getOption('client-id');
        $clientToken = $input->getOption('client-token');
        
        $api = new StitchApi($clientId, $clientToken);
        $api->validate();
        $this->api = $api;

        $row = 0;
        if (($handle = fopen($file, "r")) !== false) {
            while (($event = fgetcsv($handle, 1000, ",")) !== false) {
                $row++;

                // Skip header row
                if ($row == 1) continue;

                $this->importEvent($tableName, $event);
            }
            fclose($handle);
        }
    }

    protected function importEvent($tableName, $event)
    {
        $api = $this->api;
        
        // TODO Implement
        // $api->pushRecords(
        //     $tableName,
        //     [' id' ],
        //     [
        //         [
        //             "id" => 1,
        //             "first_name" => "Sam",
        //             "last_name" => "Minnee",
        //             "num_visits" => 3,
        //             "date_last_visit" => new Datetime("2018-06-26"),
        //         ],
        //         [
        //             "id" => 2,
        //             "first_name" => "Ingo",
        //             "last_name" => "Schommer",
        //             "num_visits" => 6,
        //             "date_last_visit" => new Datetime("2018-06-27"),
        //         ]
        //     ]
        // );
    }
}
