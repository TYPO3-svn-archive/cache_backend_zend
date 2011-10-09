<?php 
if((double)$TYPO3_CONF_VARS['SYS']['compat_version'] >= 4.6 ) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheBackends']['tx_cachebackendzend_cache_backend_zdc'] = 'typo3conf/ext/cache_backend_zend/Classes/class.tx_cachebackendzend_cache_backend_zdc.php:tx_cachebackendzend_cache_backend_zdc';
} else {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheBackends']['tx_cachebackendzend_cache_backend_zdc'] = 'typo3conf/ext/cache_backend_zend/Classes/class.tx_cachebackendzend_cache_backend_zdc_ocb.php:tx_cachebackendzend_cache_backend_zdc_ocb';
}
?>