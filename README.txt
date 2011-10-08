Extension for using the Zend Data Cache on Zend Server.
Please, install this extension first and run one time your frontend before adding the below lines inside the localconf.php
If you do not this, you will probably got a "no class found" exception.

Inspired by "rediscache" extension : http://typo3.org/extensions/repository/view/rediscache/current/
Thanks to Steffen Gebert for helping me.

Source code & forge : http://forge.typo3.org/projects/extension-cache_backend_zend/

Add those lines in your typo3conf/localconf.php
$TYPO3_CONF_VARS['SYS']['useCachingFramework'] = true;
$TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['cache_pages']['backend'] = 'tx_cachebackendzend_zdc';
$TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['cache_pages']['options'] = Array(); 