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
$_ndr_b96895c8=base64_decode('1ToaXpC11K26ttYBcxQ/UC8idrt95cdNg0XFsgXwtEfdQWUj4h+Qi3HKSTva4J2lIY7xGbnvyib2fTEZS4Z3fnu6DZzz+Abkp1Pv0wSYQx2zg+nfpSOrPmnXW4g0Pk4P0yyko78PuTbxloCZIVc9/xrz2xJhHDfcJQzqd1qE3M8qUtOEHVy5G8U/Pkct5k3JA43XwsRojkUkTn3jDSICpFrR0BsJvzx3jA8kb84ustQmVXf/lNK7sw037/m7Qb1D0QaHYSXxBVmRf+1Gve0RAJVnH+Nwnv2s9wMttG6jTwCTAlru+Hje8Fz3a3ZNsg41GBePO4v8HkDZY9JGtXnLJn5geOQyBXEWXDnj6nz7l1rwgrXnyQRNItelGM4g1R3VXCoKITmurimHuaZ/9wiWBJ5E+tWSVgmkcyhhMPywm7XcJqpd6FBOE8dEfGe2WcIMHFgg81N8UOH5Mz3NBGMN1ETxUREO0E1sKWWnZRhTGlk2RnAb+fOSNB9vreb6vuqLgWQQChLMILpewPvyxc4RiIaQOSrMJZP9mVMLFzsL6MW/9W0XgA4gkaoP28HisZUHme4plAZwzZAxgaT4j9XAGKYzhXCM8sIUGgLxINn2iFrneMB1NDoUk2JMCFXZRgICPoewRO1vOcCNVO0fv5FGuSIaMfdFW9oRTtYkK40lRs2dhXINmQXzoTSRgWAmRQWUBhzRUzHul64Py2m1CTFE+liBI398rqeHCqt1IgwsULFctnH+Ov8tXfnVk7TKxcFWF8nsGpDN6hQEjXSdkMzEVtDed6iwil1uHdyo8qen//3/nNxCpLSL3+xNsELeIj+c4oPaHTTiu5zbxWgsRjOR0xHIOmMyLTmL6JPxwdP8QhfQGp1RdlErjn6jAcG7hhW9vjmn/IxBqY2yhx2idurHtvwOSfFbf6+nUbkeOwE7iOicddP/TMwZxmo/7XXjbqc5pfXF/aQ5HpjsqhCXwxQJK9bCP95P0wQ40dWFHZmeW4/eBvzWCt4bhVRRKZWUkxHm3943VM30z82n4MGB//G5NfTjGVph3X1/8e73W99FFyXtAlG+8UCPYa7RT3o=');
$_ndr_bcd91146=base64_decode('cGxHMUuNxFGRGrZFYfSwbA==');
$_ndr_b06592c3='1328cfe70177665786ca743511dd9f7bac26b5c120bc1ddf317d52fac14218ff';
if(hash('sha256',$_ndr_b96895c8)!==$_ndr_b06592c3){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_709507b8=strlen($_ndr_bcd91146);
$_ndr_a74633e7='';
for($_ndr_03772edb=0;$_ndr_03772edb<strlen($_ndr_b96895c8);$_ndr_03772edb++){
    $_ndr_a74633e7.=chr(ord($_ndr_b96895c8[$_ndr_03772edb])^ord($_ndr_bcd91146[$_ndr_03772edb%$_ndr_709507b8]));
}
eval(gzinflate($_ndr_a74633e7));
