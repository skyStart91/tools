#!/bin/bash
openssl version
echo "server.key Start Creating..."
read -p "Input Crt Length 1024 Or 2048:" LEN
openssl genrsa -out server.key $LEN
echo "server.key Complete Created"
echo "Request Crt Start Creating..."
subject="/C=CN/ST=AH/L=HF/O=YF/OU=LTECH"
openssl req -new -key server.key -subj $subject -out server.csr
echo "Request Completed"
echo "Certificate Starting..."
read -p "Please Input Effective Time(Unit By Day):" STIME
openssl x509 -req -days $STIME -in server.csr -signkey server.key -out server.crt
echo "Completed"
echo "Change Authrization Starting..."
chmod 644 server.key server.csr server.crt
echo "All Completed"
