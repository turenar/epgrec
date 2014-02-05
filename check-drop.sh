#!/bin/bash

if [ ! -e "$1" ]; then
	echo 'not found' >&2
	exit 1
fi

exec 9>/run/tsselect.lock
flock 9

( echo "$1"; nice -n 19 ionice -c 3 /usr/local/bin/tsselect "$1" 2>/dev/null) \
	| tee >(mail -s 'drop report' root)
