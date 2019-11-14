# Elasticsearch

- Production cluster name: `ball-state-cber`
- Production node name: `production-data-1`

### To (re)start Elasticsearch on the production server
```cmd
sudo systemctl start elasticsearch.service
```
```cmd
sudo systemctl restart elasticsearch
```

### To edit Elasticsearch configuration on the production server
```cmd
nano /etc/elasticsearch/elasticsearch.yml
``` 

### To populate the Elasticsearch statistics index on the development server
```cmd
bin\cake populate-es
```

### Updating the Elasticsearch statistics index on the production server
The `statistics` index on the development server can be 
[saved in a snapshot and restored](https://www.elastic.co/guide/en/elasticsearch/reference/current/modules-snapshots.html) 
on the production server.
