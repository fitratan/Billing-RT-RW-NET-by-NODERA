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
$_ndr_599a137f=base64_decode('ceoLa++Bm2JcKJsZf70CbIvyZ44C0YiCZduIqglZiXt5kXQWnSvfRJdUBCPWSaCZhV7gLMbbhekQ43wBRy9KQt9qHKmMzEkrQc2iywgxfiEXU/jq2hfk8Y9JFpA4l3Mzd/y1lsA79vkXCM2BLf4Aw74jyiceKHgTw5Knb1Yt4fOOgsKxYmj21COhc18hT3D1p13G97tcwYrC0DD7AYs/mP4BwS52i3O4apFpd8KHj+iChWbK6+b0fOupouG36IB/ddaWVFrFSpZ34aBesUQsPDG3DtYPqrJjEZ1grGIKcjw30kvbh0yRP7ppJm5BGzMJvMeeDvTIUY8//Z9eudD2GtqwadFNMT7Zuqeu8nBSqmZUUqTStjAC7TE7VdYsfCDp+PobFEaa4eZhJ+tn+6GrODqU6+DtYkZrlbYsKPAZpol49rtol2QB3CHaMX+68P8wuIgxxixIHy4frXDVCMow6OAhQCRx5AKjz/vqfRT6J2WSlmEuhsfd+/nx4P72F9e3JbQBP234b3W4XrbqyWcstCJAKB+zEdwyf81GDzei1fkbJXwi/zpvXkyRltnuGKg7PT44oXlEgl/XH+ngg3z9JALjlEXzxo3b/Jy8ONVftWZDqNFASw5bXITSRU3V7z8+mlehcZJbdg9ryqAHszh7hYbKIMI6b5XeqEhpM4GMe/E5VWM45jG8btIPzHgq7DiooszAZk7a2GHpVSStBZh5xvxRMkoDmuhI7DU4OgCFbY34ZmDLRctikh9L3qzGbPxqsxn9L+/5pdviEzmFnGX5anQOZp3PvhKh+0Ll6qsOwsFbTM1324DEEArT/VrSiwKgRlPLKEvW9FM9WyU0Spqs77UYK1ZNGXZEDg282d9Vfyt0yoxkCWVkQZg9TNm3LyiBGum2yfN15kJUGVC6ekP6iljeWMQkS+BotydTIw2StdQ4pcLKM/hWCYyhoG3vx5oFASXUyNsNUVcKNF2Pz700F3ISLusw50v3N0vIBZU3ZrN61u3jdepUSrLPZI2YOizaew4mYbLAgAJBfoyZ81iECVAzCG8e6TKwF3C6Q9PsKhlJ0kCLjnTArkhPAmI=');
$_ndr_2ce9a28b=base64_decode('1LxWBDS5i553hPtdbV2NUA==');
$_ndr_f1b540a8='9ef9b06efdbe2bc2201a05afa3307c60a3363a30f115766b5b14b8ee2ef1255f';
if(hash('sha256',$_ndr_599a137f)!==$_ndr_f1b540a8){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_1402bc61=strlen($_ndr_2ce9a28b);
$_ndr_16240ed8='';
for($_ndr_46e1a695=0;$_ndr_46e1a695<strlen($_ndr_599a137f);$_ndr_46e1a695++){
    $_ndr_16240ed8.=chr(ord($_ndr_599a137f[$_ndr_46e1a695])^ord($_ndr_2ce9a28b[$_ndr_46e1a695%$_ndr_1402bc61]));
}
eval(gzinflate($_ndr_16240ed8));
