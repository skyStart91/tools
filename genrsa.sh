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
#这里的P一定得是完整的路径名包括最后一个斜杆/
read -p "Move Certificate To Target Path : " P
mv server.* $P
echo "Change Authrization Starting..."
chmod 644 ${P}server.key ${P}server.csr ${P}server.crt
echo "All Completed"
