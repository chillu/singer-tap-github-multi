<?php
namespace Chillu\SingerGithubMulti\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Sminnee\StitchData\StitchApi;

/**
 * Splits up into batches for more efficient processing.
 * Uses special type tables for payloads, to avoid overloading
 * the base events table with too many columns (slow DB queries).
 */
class ImportBigQueryCommand extends Command
{
    
    /**
     * @var StitchApi
     */
    protected $api;
        
    protected function configure()
    {
        $this
            ->setName('import-bigquery')
            ->setDescription('Imports a Google Bigquery JSON export into Stitchdata')
            ->addArgument('file', InputArgument::REQUIRED, 'JSON file to import')
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Start later in the file', 0)
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Size of batches sent to Stitchdata', 50)
            ->addOption('client-id', null, InputOption::VALUE_REQUIRED, 'Stitchdata Client ID')
            ->addOption('client-token', null, InputOption::VALUE_REQUIRED, 'Stitchdata Client Token');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $offset = $input->getOption('offset');
        $batchSize = $input->getOption('batch-size');
        $clientId = $input->getOption('client-id');
        $clientToken = $input->getOption('client-token');
        
        $api = new StitchApi($clientId, $clientToken);
        $api->validate();
        $this->api = $api;

        // Efficiently walk through JSON
        // Every loop causes two event writes (one base table and one for type specific tables)
        // See limitations at https://www.stitchdata.com/docs/integrations/import-api
        $batchCount = 0;
        $batch = [];
        $currLine = 0;
        if (($handle = fopen($file, "r")) !== false) {
            while (($data = fgets($handle, 1024*1024)) !== false) {
                $currLine++;

                if ($currLine < $offset) {
                    continue;
                }

                $event = json_decode($data, true);
                $event = $this->convertDataTypes($event);
                $eventTypeTable = strtolower(preg_replace('/Event$/', '', $event['type']));

                // Polyfill unique identifier (required for stitch).
                // Older records in Github don't have this (>2015).
                // Assumes those events are immutable to avoid duplicates on repeat imports.
                if (!isset($event['id'])) {
                    $event['id'] = md5(json_encode($event));
                }

                echo sprintf("Processing event id %s\n", $event['id']);

                // Decode separate payload (inlined via Google Bigquery)
                if (isset($event['payload'])) {
                    $event['payload'] = json_decode($event['payload'], true);
                }

                // Base table batch
                $batch['events'] = $batch['events'] ?? [];
                $batch['events'][] = $this->getBaseEvent($event);

                // One batch for each type
                $batch["events_{$eventTypeTable}"] = $batch["events_{$eventTypeTable}"] ?? [];
                $batch["events_{$eventTypeTable}"][] = $this->getTypeEvent($event);

                $batchCount++;
                
                if ($batchCount >= $batchSize) {
                    $this->pushEvents($batch);
                    $batch = [];
                    $batchCount = 0;
                }
            }

            // Push last events
            $this->pushEvents($batch);

            fclose($handle);
        }
    }

    /**
     * @param array $batch
     * @return void
     */
    protected function pushEvents($batch)
    {
        foreach ($batch as $table => $events) {
            $this->api->pushRecords($table, ['id'], $events);
        }
    }

    /**
     * Add some type hints for singer
     *
     * @param array $event
     * @return array
     */
    protected function convertDataTypes($event)
    {
        // Pass by reference, sigh
        array_walk_recursive($event, function (&$item, $key) {
            if (preg_match('/_at$/', $key)) {
                $item = new \DateTime($item);
                return;
            }
        });
        
        return $event;
    }

    /**
     * @param array $event
     * @return array
     */
    protected function getBaseEvent($event)
    {
        if (isset($event['payload'])) {
            unset($event['payload']);
        }

        return $event;
    }

    /**
     * @param array $event
     * @return array
     */
    protected function getTypeEvent($event)
    {
        return array_merge(
            ['id' => $event['id']],
            $event['payload']
        );
    }
}
