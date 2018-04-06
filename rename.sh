#!/bin/sh

find . -name "*.php" -exec sed -i 's/'$1'/\\'$1'/g'  {} \;
find . -name "*.php" -exec sed -i 's/\\\\'$1'/\\'$1'/g'  {} \;
