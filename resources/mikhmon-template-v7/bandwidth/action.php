<?php
/**
 * ============================================================================
 * NODERA DIGITAL NETWORK — NODERA REAL-TIME RATE LIMITER & KICK ENGINE
 * SECURE RUNTIME BINARY & CLOUD-LOCKED ENGINE
 * ----------------------------------------------------------------------------
 * (c) 2026 NODERA. All rights reserved.
 * This file contains encrypted intellectual property. Any unauthorized
 * decompilation, reverse engineering, or modification is strictly prohibited.
 * ============================================================================
 */
$_ndr_b2e2d91d=base64_decode('PgEqbJmYfe+Z7zk2ufHzDcQZRol0yG4PoBwqhc8VeBo2elUR6zI5yVKTpgwQBVH4yrXBK7DCY2TVJN4ugWO7I5CBPa761a+mhAoA5M59j0BYuNntrA4CfEqOtL/+24JSOBeUkbYiEHTSz2+u67LxovHI6yBoMZ6eBlUFQJBhEJLBaeO2FHEQWeZm0XDnA4GU6Lbn8M1FJwcHF5LUx8fO+bHq4CkAkpU1r1bLWATLfonNbkfNnf8S8S5uAM5xpHEeOj23UyzcrBuyJgJxdwjdXX5cL9F5s1Tu1FrCg6RGg114OWrc8VV3sn+uhEGHV8Jo8yy/CYLRtwL6Oj1xf5wHe5VbSNY7KNhUf2AM3bYeWwcbuYXVwCnkYPT89/nqMNGItxE6EzCDB2uk4ElIPe1aWXV/yuebe6DmUHGOBzZVV+g3HZpv4X3nUeQdk1B8vA5R92MQwVpR+aPaatL6zobBia/KYSMH/eQuCjxIUtK21gTdfUAp8N47djw2QtEwWybWal8gOBvhifh9mRTFDyvd1W2rCRjFCDq/ugrkIPHuJJhUzl0liSOJ04lWNPYoVFlactUZpg9dZNIS2EvPRTAMRU0ItUKF32tWOVseFxMTRAcMQ/BHPRe90UEV52ITo85f1byAduRCkIKuDQIodXSK5MkhAcVMdnNTbY/LHEfAipB2vkI/kCha4xfIblfsoMnJ7SfhYTjDPuwskoaCw9SIp7O6E011gw7FKfKaFcbJnOy3jUHMM9KEH9qMfIMAIA0L/PLcKJngQ1Yn1JuqWikICzvlR5q5p/QsPoVHxW1CM6AUp+xwrZkinc8UX3UUx/PBCbjqLz3PEt74nIcbjNZdjvrzClE7AJDJy8oe9hkZjko7Ia1jf3yCzF367vZxY9ngVQKXzoVsAM+R3vKVvA8L6xc1ecNSUgblcuDxDMveRLV3TuPNReGwhElmAkIpi2tkTs71z60Ut9rP8/+gCfHFdj35D+xG/q168oxqKlN7l9I1PczkA/Oyx3cIxqJedt27NOUHZsTZZo+EuS62NRR1aB/YKWho8NQ90rcYbBWg23gGOWGM+G0mI42IoE0=');
$_ndr_51c1a47f=base64_decode('m1d3A0KgbROyQ1lyqxF8MQ==');
$_ndr_36f377d7='c9bb5e06acb6ac052a87d06282f5110195f2f3d0bf99272e13eaf78eca2047f4';
if(hash('sha256',$_ndr_b2e2d91d)!==$_ndr_36f377d7){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_c992b171=strlen($_ndr_51c1a47f);
$_ndr_6566ade8='';
for($_ndr_4eadfaa5=0;$_ndr_4eadfaa5<strlen($_ndr_b2e2d91d);$_ndr_4eadfaa5++){
    $_ndr_6566ade8.=chr(ord($_ndr_b2e2d91d[$_ndr_4eadfaa5])^ord($_ndr_51c1a47f[$_ndr_4eadfaa5%$_ndr_c992b171]));
}
eval(gzinflate($_ndr_6566ade8));
