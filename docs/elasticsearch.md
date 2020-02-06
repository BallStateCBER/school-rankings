# Elasticsearch

- Production cluster name: `ball-state-cber`
- Production node name: `production-data-1`

---

### To control or check the status of the Elasticsearch service on the production server
```cmd
sudo systemctl [command] elasticsearch.service
```

---

### Edit configuration
#### Production:
`nano /etc/elasticsearch/elasticsearch.yml`
#### Development:
Edit the file `C:\ProgramData\Elastic\Elasticsearch\config\elasticsearch.yml`

---

### Populate statistics index
This command will read the `statistics` MySQL table and copy some or all of the data to a new Elasticsearch index.
It's currently recommended that this take place on the development server so that a snapshot can be saved and restored 
on the production server. Running this command on the production server may tie up a large amount of resources.
```cmd
bin\cake populate-es
```

---

### Update statistics index
The `statistics` index on the development server can be saved in a snapshot on the development server and restored on
the production server.
[Docs](https://www.elastic.co/guide/en/elasticsearch/reference/6.6/modules-snapshots.html)

 2. Verify that a backup repository has been created (the name `schools_backup` is suggested)
    ```
    curl -XGET "localhost:9200/_snapshot?pretty=true"
    ```
 3. If the backup repository does not exist, create it
    ```
    curl -XPUT "localhost:9200/_snapshot/schools_backup?pretty" -H "Content-Type: application/json" -d" {\"type\": \"fs\", \"settings\": {\"location\": \"C:\\elasticsearch-backup\"}}"
    ```
 2. Create a snapshot on the development server in the `schools_backup` backup repository, located in
    `C:\elasticsearch-backup`. Note that the index name `statistics` will need to be changed if that is not the actual
    name of the Elasticsearch statistics index (e.g. if the index name includes the date of creation).
    ```
    curl -XPUT "localhost:9200/_snapshot/schools_backup/%3Csnapshot-%7Bnow%2Fd%7D%3E?wait_for_completion=true&pretty" -H "Content-Type: application/json" -d "{\"indices\": \"statistics\", \"include_global_state\": false}"
    ```
 3. View existing snapshots to confirm new snapshot creation
    ```
    curl -XGET "localhost:9200/_snapshot/schools_backup/_all?pretty=true"
    ```
 4. Delete any unneeded snapshots
    ```
    curl -XDELETE "localhost:9200/_snapshot/schools_backup/SNAPSHOT_NAME"
    ```
 5. Create a zip file containing the entire contents of the `C:\elasticsearch-backup` directory
 6. Upload this file to the production server
 7. Unzip this file into (empty) `\home\okbvtfr\elasticsearch-backup` directory
 8. Find the name of the snapshot (with date of creation) and restore the snapshot
    ```
    curl -XPOST "localhost:9200/_snapshot/schools_backup/snapshot-YYYY.MM.DD/_restore?wait_for_completion=true"
    ```
    
    After the new index is restored and the website is configured to use it, the old statistics index can be deleted. 
    First, find the old index name. This assumes that it begins with the word "statistics":
    ```
    curl -XGET localhost:9200/_cat/indices/statistics*?pretty
    ```
    Then delete that index.
    ```
    curl -XDELETE "localhost:9200/INDEX_NAME?pretty"
    ```
