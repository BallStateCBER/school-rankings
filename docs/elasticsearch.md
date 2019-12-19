# Elasticsearch

- Production cluster name: `ball-state-cber`
- Production node name: `production-data-1`

---

### To (re)start Elasticsearch on the production server
```cmd
sudo systemctl start elasticsearch.service
```
```cmd
sudo systemctl restart elasticsearch
```

---

### Edit configuration
#### Production:
`nano /etc/elasticsearch/elasticsearch.yml`
#### Development:
Edit the file `C:\ProgramData\Elastic\Elasticsearch\config\elasticsearch.yml`

---

### Populate statistics index
#### Development:
```cmd
bin\cake populate-es
```

---

### Update statistics index
The `statistics` index on the development server can be saved in a snapshot on the development server and restored on
the production server. [Docs](https://www.elastic.co/guide/en/elasticsearch/reference/current/modules-snapshots.html)
