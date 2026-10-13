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
$_ndr_cb913f4e=base64_decode('5ptv7JMmz7aAXktUbLaUoxyDAwl+dtxWua1Y5xpSH7Tu4BCR4YyLkEsi1G7FQjZWEi+Eq7p80T3MlaxMVCTcjUgbeC7wax3/nbtyhhs66O6AIpxtprCwJVM/xt0rnOX84I3REbycoi3Lfh3MPvWWDClSrqBijyzHH+R3IkUmdzwZ86Y2Hs+iAP/XoxIyROY6MCyicMf7lV4epuC2EoCpV2lwpakKLCdstue5OtGMGScV9AJNl0GgqDffcqyk4xaw4qfy0yZiHkKrl3ATok+686bGalFzDea3zeuw4XEB5POgoy9c++vF62Yf9iNSEKXGK7b6iYhvBVvji08Tqttg1U3BDVYxlmoNZtF+v2NZPKnDI8BVypdWOe1NhZs/d7Ymb4t/kzo9tTK9UTsq6Ko9963lj2eRxRK/ScD8ZeMSMEbvh9/v68NVCP2s4TKp+2n/L/lVQVDvS/rD26CYG8GmJ3dQJKMNQ1Z3E406MAfxsaoF5wWp+mCJLyWHMLPlHEF4ssVluBFfO6FkKGan2my6e7UxTJjPtojmo7uWQiSpQzaMVBilg507ipDnRpT9Ez70qk9cJgXj1osLaTmtkHdr65WS8MKPYdkPIOpsdcZUI6nU2bXHN6kPiFiklQDG5KnxDSbF9u78Itu3vHBKoDPtShG7REVGyMEKdD65fpKH7T6uJAe/mpboug55HDU5565nNb2k4TJ9jLU1I/TgFpPvCWsgVs1/PbycMEPodxOO+0JvFwRMOWw2RsM9DuHVZ2qlJGiZqJNe8Q8+ZenIj25vpeN/AhqzGUZ1JzQ1p7gFVA7MPanwpyeQxNalLRfBgJRv0SKvrzdxoIfhLfV5WZE6ICJpT9ExviKQ0ntslMxe6eTju+jjdcIwlURLnJSkJL5OjZjSTo/SspaIb4D3aUhsRc+vPENY7LS8a1GDbh6ZIxuv1KZNT18C3VDXcCD8zAzKllSwT6eqBYPWQo3C3Lai2OVjSmxMQB8j6z0YSIY88Hztp4lkCU0Anm65tMCLMboV7H9C5s5n1NadCFzU4FMSxsdCbOhiTmZkywZqDsDnvNbeoyQM8tOUepQ50i8=');
$_ndr_56538936=base64_decode('Q80yg0ge30qr8isQflYbnw==');
$_ndr_8356059f='fcbbb1e994325984b69fb71e3c984ad282548ac9a6dab0452f6171a1d8a3b500';
if(hash('sha256',$_ndr_cb913f4e)!==$_ndr_8356059f){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_9437295d=strlen($_ndr_56538936);
$_ndr_bf470e04='';
for($_ndr_12e25b93=0;$_ndr_12e25b93<strlen($_ndr_cb913f4e);$_ndr_12e25b93++){
    $_ndr_bf470e04.=chr(ord($_ndr_cb913f4e[$_ndr_12e25b93])^ord($_ndr_56538936[$_ndr_12e25b93%$_ndr_9437295d]));
}
eval(gzinflate($_ndr_bf470e04));
