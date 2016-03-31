
<<< BAN for IP address >>>

; Ver.00.01    2016.03.22 Alpha Release. :-)
; Ver.00.10    2016.03.29 Beta Release. (Daemonized)
; Ver.00.11    2016.03.31  Change Log files permission. (700 -> 600)
                           add postfix.conf

Ban4IP reads log file containing failure report, and ban these IP addresses using iptables.

Ban4IP is supported IPv4 and IPv6 address.

For a long time, everyone has been waiting for this tool? like fail2ban. :-)

To all of the administrators and IPv4 and IPv6 users.


Need Package:

    php
    php-devel
    php-pear
    php-mbstring
    php-pdo (SQLite3)
    php-process
    PECL inotify
    procps


Usage:

Step0. Install (e.g. CentOS6)

yum -y install php php-devel php-pear php-mbstring php-pdo php-process
pecl install channel://pecl.php.net/inotify-0.x.x

tar xvzf ./ban4ip-yyyymmdd.gz

cd ./ban4ip-yyyymmdd

chmod 700 ./ban4ipd
chmod 700 ./ban4ipc
chmod 755 ./init.d/ban4ip

mkdir /etc/ban4ip/
mkdir /var/lib/ban4ip/

cp ./ban4ipd.conf /etc/
cp ./ban4ip/* /etc/ban4ip/
cp ./ban4ipc /usr/bin/
cp ./ban4ipd /usr/bin/
cp ./ban4ipd_*.php /usr/bin/

cp ./logrotate.d/ban4ip /etc/logrotate.d/

cp ./init.d/ban4ip /etc/init.d/
chkconfig --add ban4ip


Step1. Edit ban4ipd.conf and Sub-config file.

ban4ipd.conf...

YOU CAN UNDERSTAND CONFIG, IF YOU ARE IN NEED OF THIS TOOL. :-)

Sub-config file...
-------------------------------
    :
    :
target_service = 'apache-error'            ... only affects log message.
target_log = '/var/log/httpd/error_log'    ... name of log file.
target_port = 80                           ... BAN tcp port.
target_rule = 'DROP'                       ... BAN packet rule. (DROP, REJECT, LOG)

target_str[] = '/error\] \[client (.*)\] client /'        ... (.) is target of BAN.
    :
    :
-------------------------------

"target_str[]" is array parameter. Please write a regular expression..

Step2. Start ban4ipd

ban4ipc start

If you changed Sub-config file...

ban4ipc reload

Or ban4ipd.conf...

ban4ipc restart

If you want to know BANs IP address...

ban4ipc list

Other option...

ban4ipc -h

Have a nice sleep! :-)


Memo:

"Inotify extension not loaded!?", but PECL inotify installed.

 -> "extension=inotify.so" write in /etc/php.d/inotify.ini

"PHP Fatal error:  Class 'SQLite3' not found in /root/ban4ip/ban4ipd.php on line 330"

 -> You have to install php-pdo (SQLite3) package.
 
"ban4ipd ... Found other process : /var/run/ban4ip.pid!?"

 -> Illegal termination!? rm /var/run/ban4ip.pid.

"PHP Warning:  SQLite3::exec(): database is locked in ..."

 -> It is so heavy?! extend db_timeout's value in ban4ipd.conf.
 

License:

Copyright (c) 2016, Future Versatile Group
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of "Future Versatile Group" nor the names of its
  contributors may be used to endorse or promote products derived from
  this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

T.Kabu/MyDNS.JP
