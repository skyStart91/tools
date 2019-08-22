#!/bin/bash
openssl version
echo "server.key Start Creating..."
#20s没有输入就使用默认，默认是2048
read -p "Input Crt Length 1024 Or 2048 (Default 2048):" -t 20 LEN
openssl genrsa -out server.key $LEN
echo "server.key Complete Created"
echo "Request Crt Start Creating..."
subject="/C=CN/ST=AH/L=HF/O=YF/OU=LTECH"
openssl req -new -key server.key -subj $subject -out server.csr
echo "Request Completed"
echo "Certificate Starting..."
#20s没有输入则使用默认，默认是空,如果此处使用默认则会报错
read -p "Please Input Effective Time(Unit By Day):" -t 20 STIME
openssl x509 -req -days $STIME -in server.csr -signkey server.key -out server.crt
echo "Completed"
echo "Change Authrization Starting..."
chmod 644 server.key server.csr server.crt
echo "All Completed"
