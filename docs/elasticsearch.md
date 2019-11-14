# Elasticsearch Commands

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

### To populate the Elasticsearch statistics index
```cmd
bin\cake populate-es
```
