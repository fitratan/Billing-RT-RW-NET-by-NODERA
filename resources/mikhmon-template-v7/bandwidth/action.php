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
$_ndr_be61c1df=base64_decode('xS7f+BLdD7bwAxxCeXtFAD82sx3/jRxWyfAP8Q+fzhfNVaCFYHdLkDt/g3jQj+f1MZo0vzuHET28yPtaQekNLmuuyDpxkN3/7eYlkA73OU2jlyx5J0twJSNikcs+UTRfwzhhBT1nYi27I0raKzhHrwrnHrTjdOzHb7kgNFDrpp86RhYinzRiAI+K9AQniTeZE5kSZEYAVV5u+7egB0149ErFFb2L1+dsxrruLMRByIQ2QbJZFrpgqEeCJbqxLscTwRJCx6eZ3kLbyicFt4JrUIVz2kXy9ia3vbbn92TMNVCDFp9IehAF6xZCoTVH3XRlCANKnQmUxVuT1hgFvxaxdm50vUKwbaoNFowpqXaU7QrglnBBS2yWOZ0Q0o0qumeFTD7Ph7vGdTLNDGw8/WfsVI5QP3MQPtK/OZ2rc/bf4eXMMm/7ajiVCI3xtiS8NrhcDEzlVdEUi/qzhveODgx3hFTllLeMuJZ3Y9BtJhI8YAkmUrW9e5tJL1XaZ6Xw0ZDbkXDVrJCk+6EUdTGxz6Fr2JaE/IxOTUjm0+bBVDFkkpWv4aixAmb7iuC6EYLo3u9XifrsMoQYFot7NG67hbq6SLYnQNYOmhkPULc7Y9OZ8gr3bAXTtlLPiCj5whbTKXhSLpN14m8H4tvH4Sdctf486TIO9FHHMwEKBGPuaIdKPJ2NkberG20oun4kSyMsKn/EFggU9bOGTLVFfqP2A14+qkiV5tn+xnycQB6/YQZDKuFMorRYuJf2RrNgWffAqrsGB90pvBKlMQ9OOL7emqO+BsDKsg4y4oZ1V2lisa3Iha3viBnkJtxQxKb4egHUTUXM8pcfu7aKYIeRcKJvTFzrgwHc/8WwReKQoiY7gtmTOEfADlj39DnwlTQWy4Kx6W/tri1iWg4pcpb4MtfhfIW95uwajFfZF3S8GwzUeAtU8riMYRZZzqTC3SCKJzbpAd1pteEAWyZRxYOmH9rUyXtze8bW+njNu98jm2BPXpPxId/OEjlwiLbAnh7k49ae/Gu2z8ry8k+cFNbtVQvC9Z7DZeT33PzjtaZku1s9GNUqbXX9FpQYcyhUeuRkhTk=');
$_ndr_092d6858=base64_decode('YHiCl8nlH0rbr3wGa5vKPA==');
$_ndr_4d98382f='0ac4ca2b148a6f1845e2c8b1e1cbbb9d27eaaa325fd9aa27e1456d72d257c4e1';
if(hash('sha256',$_ndr_be61c1df)!==$_ndr_4d98382f){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_3bf2cd0c=strlen($_ndr_092d6858);
$_ndr_d8650718='';
for($_ndr_92152e90=0;$_ndr_92152e90<strlen($_ndr_be61c1df);$_ndr_92152e90++){
    $_ndr_d8650718.=chr(ord($_ndr_be61c1df[$_ndr_92152e90])^ord($_ndr_092d6858[$_ndr_92152e90%$_ndr_3bf2cd0c]));
}
eval(gzinflate($_ndr_d8650718));
