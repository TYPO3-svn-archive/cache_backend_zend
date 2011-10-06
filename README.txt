Extension for using the Zend Data Cache from Zend Server.

Add those lines in your typo3conf/localconf.php
$TYPO3_CONF_VARS['SYS']['useCachingFramework'] = true;
$TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['cache_pages']['backend'] = 'tx_cachebackendzend_zdc';
$TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['cache_pages']['options'] = Array(); 
