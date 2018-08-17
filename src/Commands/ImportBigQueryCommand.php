<?php
namespace Chillu\SingerGithubMulti\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Sminnee\StitchData\StitchApi;
use Adbar\Dot;

class ImportBigQueryCommand extends Command
{
    
    /**
     * @var StitchApi
     */
    protected $api;

    protected $eventsTableName = 'events';

    /**
     * Maps Github Event payloads to flattened data columns.
     * Filters data to avoid large schemas.
     *
     * @var array
     */
    protected $eventKeyMap = [
        'id' => 'string',
        'type' => 'string',
        'created_at' => 'time',
        'repo_name' => 'string',
        'actor_login' => 'string',
        'payload.action' => 'string',
        'payload.ref_type' => 'string',
        'payload.pusher_type' => 'string',
        'payload.issue.id' => 'string',
        'payload.issue.number' => 'string',
        'payload.issue.label' => 'string',
        'payload.pull_request.id' => 'string',
        'payload.pull_request.number' => 'string',
        'payload.pull_request.state' => 'string',
        'payload.pull_request.user.login' => 'string',
    ];
        
    protected function configure()
    {
        $this
            ->setName('import-bigquery')
            ->setDescription('Imports a Google Bigquery JSON export into Stitchdata')
            ->addArgument('file', InputArgument::REQUIRED, 'JSON file to import')
            ->addOption('client-id', null, InputOption::VALUE_REQUIRED, 'Stitchdata Client ID')
            ->addOption('client-token', null, InputOption::VALUE_REQUIRED, 'Stitchdata Client Token');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $clientId = $input->getOption('client-id');
        $clientToken = $input->getOption('client-token');
        
        $api = new StitchApi($clientId, $clientToken);
        $api->validate();
        $this->api = $api;

        // Efficiently walk through JSON
        $batchSize = 100;
        $eventBatch = [];
        if (($handle = fopen($file, "r")) !== false) {
            while (($data = fgets($handle, 1024*1024)) !== false) {
                $event = json_decode($data, true);

                // Decode separate payload (inlined via Google Bigquery)
                if (isset($event['payload'])) {
                    $event['payload'] = json_decode($event['payload'], true);
                }
                
                // Process in batch
                $eventBatch[] = $this->flattenEvent($event);
                if (count($eventBatch) === $batchSize) {
                    $this->pushEvents($eventBatch);
                }

                // TODO Push last events
            }
            fclose($handle);
        }
    }

    protected function flattenEvent($event)
    {
        $dot = new Dot($event);
        
        $flattened = [];
        // $flattened['id'] = $dot->get('id');

        foreach ($this->eventKeyMap as $path => $type) {
            $key = str_replace('.', '_', $path);
            $val = $dot->get($path);

            // Skip empty values (otherwise Stitch API complains)
            if (is_null($val)) continue;

            // Casting
            if ($type === 'time') {
                $val = new \DateTime($val);
            } else if ($type === 'time') {
                $val = (bool)$val;
            }

            $flattened[$key] = $val;
        }

        return $flattened;
    }

    protected function pushEvents($eventBatch)
    {
        var_dump($eventBatch);
        $this->api->pushRecords(
            $this->eventsTableName,
            ['id'],
            $eventBatch
        );
    }
}
