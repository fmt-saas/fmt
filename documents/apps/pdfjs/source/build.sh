#!/bin/bash 
zip -r web.app * 
cat web.app | md5sum | awk '{print $1}' > version
