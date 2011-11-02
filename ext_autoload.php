<?php 

$extensionPath = t3lib_extMgm::extPath('cache_backend_zend').'Classes/';

return array(
	'tx_cachebackendzend_cache_backend_zdc' => $extensionPath . 'class.tx_cachebackendzend_cache_backend_zdc.php',
	'tx_cachebackendzend_cache_backend_zdc_ocb' => $extensionPath . 'class.tx_cachebackendzend_cache_backend_zdc_ocb.php'
);


?>