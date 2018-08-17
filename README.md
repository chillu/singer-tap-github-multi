# Github Multi Repo Singer Integration

## Manual Import from BigQuery

Depending on how many repos you're analysing,
there can be gigabytes of historical event data.
Rather than paging through thousands of API responses,
it can be useful to start this process through a batch import
from [githubarchive](http://www.githubarchive.org/),
which syncs all global github activity to a [Google BigQuery](https://cloud.google.com/bigquery/)
data set.

Note: Check BigQuery billing before you run queries,
it can get quite expensive (dollars per query).

1. Activate Google BigQuery, and install the [Google Commandline Cloud SDK](https://cloud.google.com/bigquery/docs/quickstarts/quickstart-command-line)
1. Login to the [Google BigQuery Console](https://cloud.google.com/bigquery/)
1. Create a new destination table (replace values):
   ```bq mk <my-dataset>.<my-table>```
1. Adjust date range in `import.sql`.
   It will get all event data. Adjust the date ranges accordingly.
1. Copy query results into the destination table (replace values):
   ```cat import.sql | bq query --destination_table bq mk <my-dataset>.<my-table>```
1. Extract table into CSV
   ```bq extract <my-dataset>.<my-table> gs://<my-bucket>/<my-table>.csv```
1. Download CSV
   ```gsutil cp gs://<my-bucket>/<my-table>.csv .```

Now you're ready to import into Stichdata!

1. Set up a new Stitchdata "Import API" integration, note table name and token for use below
1. Get the Stitchdata [client ID](https://www.stitchdata.com/docs/integrations/import-api#client-id)
1. Run importer
   ```STITCHDATA_CLIENT_ID=<id> STITCHDATA_ACCESS_TOKEN=<token> bin/singer-github import-csv <stitchdata-table-name> <my-table>.csv```