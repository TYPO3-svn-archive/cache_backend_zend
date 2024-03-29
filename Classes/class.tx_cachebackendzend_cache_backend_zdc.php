<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2011 Benoit NORRIN <benoit@norrin.fr>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


/**
 * A caching backend which stores cache entries by using Zend Data Cache.
 *
 * This backend uses the following types of keys:
 * - tag_xxx
 *   xxx is tag name, value is array of associated identifiers identifier. This
 *   is "forward" tag index. It is mainly used for obtaining content by tag
 *   (get identifier by tag -> get content by identifier)
 * - ident_xxx
 *   xxx is identifier, value is array of associated tags. This is "reverse" tag
 *   index. It provides quick access for all tags associated with this identifier
 *   and used when removing the identifier
 *
 * Each key is prepended with a prefix. By default prefix consists from two parts
 * separated by underscore character and ends in yet another underscore character:
 * - "TYPO3"
 * - MD5 of path to TYPO3 and user running TYPO3
 * This prefix makes sure that keys from the different installations do not
 * conflict.
 *
 *
 * @package TYPO3

 * @api
 * @version $Id$
 */

class tx_cachebackendzend_cache_backend_zdc extends t3lib_cache_backend_AbstractBackend {

	/**
	 * A prefix to seperate stored data from other data possible stored in the ZDC
	 *
	 * @var string
	 */
	protected $identifierPrefix;

	/**
	 * Constructs this backend
	 *
	 * @param 
	 * @param array $options Configuration options - unused here
	 */
	public function __construct($context, array $options = array()) {
		if (!extension_loaded('Zend Data Cache')) {
			throw new t3lib_cache_Exception(
				'The PHP extension "Zend Data Cache" must be installed and loaded in order to use the Zend Data Cache backend.',
				1232985415
			);
		}
		if((double)$TYPO3_CONF_VARS['SYS']['compat_version'] >= 4.6 ) {
			parent::__construct($context, $options);
		} else {
			parent::__construct($options);
		}
	}

	/**
	 * Initializes the identifier prefix when setting the cache.
	 *
	 * @param t3lib_cache_frontend_Frontend $cache The frontend for this backend
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function setCache(t3lib_cache_frontend_Frontend $cache) {
		parent::setCache($cache);
		$processUser = extension_loaded('posix') ? posix_getpwuid(posix_geteuid()) : array('name' => 'default');
		$pathHash = t3lib_div::shortMD5(PATH_site . $processUser['name'], 12);
		$this->identifierPrefix = 'TYPO3_' . $pathHash;
	}

	/**
	 * Saves data in the cache.
	 *
	 * @param string $entryIdentifier An identifier for this specific cache entry
	 * @param string $data The data to be stored
	 * @param array $tags Tags to associate with this cache entry
	 * @param integer $lifetime Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited liftime.
	 * @return void
	 * @throws t3lib_cache_Exception if no cache frontend has been set.
	 * @throws InvalidArgumentException if the identifier is not valid
	 * @throws t3lib_cache_exception_InvalidData if $data is not a string
	 * @author Christian Jul Jensen <julle@typo3.org>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	public function set($entryIdentifier, $data, array $tags = array(), $lifetime = NULL) {
		if (!$this->cache instanceof t3lib_cache_frontend_Frontend) {
			throw new t3lib_cache_Exception(
				'No cache frontend has been set yet via setCache().',
				1232986818
			);
		}

		if (!is_string($data)) {
			throw new t3lib_cache_exception_InvalidData(
				'The specified data is of type "' . gettype($data) . '" but a string is expected.',
				1232986825
			);
		}

		$tags[] = '%ZDCBE%' . $this->cacheIdentifier;
		$expiration = $lifetime !== NULL ? $lifetime : $this->defaultLifetime;

		$success = zend_disk_cache_store($this->identifierPrefix . $entryIdentifier, $data, $expiration);
		if ($success === TRUE) {
			$this->removeIdentifierFromAllTags($entryIdentifier);
			$this->addIdentifierToTags($entryIdentifier, $tags);
		} else {
			throw new t3lib_cache_Exception(
				'Could not set value.',
				1232986877
			);
		}
	}

	/**
	 * Loads data from the cache.
	 *
	 * @param string $entryIdentifier An identifier which describes the cache entry to load
	 * @return mixed The cache entry's content as a string or FALSE if the cache entry could not be loaded
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	public function get($entryIdentifier) {
		return zend_disk_cache_fetch($this->identifierPrefix . $entryIdentifier);
	}

	/**
	 * Checks if a cache entry with the specified identifier exists.
	 *
	 * @param string $entryIdentifier An identifier specifying the cache entry
	 * @return boolean TRUE if such an entry exists, FALSE if not
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	public function has($entryIdentifier) {
		return zend_disk_cache_fetch($this->identifierPrefix . $entryIdentifier);
	}

	/**
	 * Removes all cache entries matching the specified identifier.
	 * Usually this only affects one entry but if - for what reason ever -
	 * old entries for the identifier still exist, they are removed as well.
	 *
	 * @param string $entryIdentifier Specifies the cache entry to remove
	 * @return boolean TRUE if (at least) an entry could be removed or FALSE if no entry was found
	 * @author Christian Jul Jensen <julle@typo3.org>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	public function remove($entryIdentifier) {
		$this->removeIdentifierFromAllTags($entryIdentifier);
		return zend_disk_cache_delete($this->identifierPrefix . $entryIdentifier);
	}

	/**
	 * Finds and returns all cache entry identifiers which are tagged by the
	 * specified tag.
	 *
	 * @param string $tag The tag to search for
	 * @return array An array with identifiers of all matching entries. An empty array if no entries matched
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	public function findIdentifiersByTag($tag) {
		$identifiers = zend_disk_cache_fetch($this->identifierPrefix . 'tag_' . $tag);

		if ($identifiers === FALSE) {
			return array();
		} else {
			return (array) $identifiers;
		}
	}

	/**
	 * Finds and returns all cache entry identifiers which are tagged by the
	 * specified tags.
	 *
	 * @param array Array of tags to search for
	 * @return array An array with identifiers of all matching entries. An empty array if no entries matched
	 * @author Ingo Renner <ingo@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	public function findIdentifiersByTags(array $tags) {
		$taggedEntries = array();
		$foundEntries = array();

		foreach ($tags as $tag) {
			$taggedEntries[$tag] = $this->findIdentifiersByTag($tag);
		}

		$intersectedTaggedEntries = call_user_func_array('array_intersect', $taggedEntries);

		foreach ($intersectedTaggedEntries as $entryIdentifier) {
			if ($this->has($entryIdentifier)) {
				$foundEntries[$entryIdentifier] = $entryIdentifier;
			}
		}

		return $foundEntries;
	}

	/**
	 * Finds all tags for the given identifier. This function uses reverse tag
	 * index to search for tags.
	 *
	 * @param string $identifier Identifier to find tags by
	 * @return array Array with tags
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	protected function findTagsByIdentifier($identifier) {
		$success = FALSE;
		$tags = zend_disk_cache_fetch($this->identifierPrefix . 'ident_' . $identifier);

		return ($tags ? (array) $tags : array());
	}

	/**
	 * Removes all cache entries of this cache.
	 *
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function flush() {
		if (!$this->cache instanceof t3lib_cache_frontend_Frontend) {
			throw new t3lib_cache_Exception(
				'Yet no cache frontend has been set via setCache().',
				1232986971
			);
		}

		$this->flushByTag('%ZDCBE%' . $this->cacheIdentifier);
	}

	/**
	 * Removes all cache entries of this cache which are tagged by the specified
	 * tag.
	 *
	 * @param string $tag The tag the entries must have
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function flushByTag($tag) {
		$identifiers = $this->findIdentifiersByTag($tag);

		foreach ($identifiers as $identifier) {
			$this->remove($identifier);
		}
	}

	/**
	 * Removes all cache entries of this cache which are tagged by the specified tag.
	 *
	 * @param array	The tags the entries must have
	 * @return void
	 * @author Ingo Renner <ingo@typo3.org>
	 */
	public function flushByTags(array $tags) {
		foreach ($tags as $tag) {
			$this->flushByTag($tag);
		}
	}

	/**
	 * Associates the identifier with the given tags
	 *
	 * @param string $entryIdentifier
	 * @param array $tags
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Dmitry Dulepov <dmitry.@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	protected function addIdentifierToTags($entryIdentifier, array $tags) {
		foreach ($tags as $tag) {
				// Update tag-to-identifier index
			$identifiers = $this->findIdentifiersByTag($tag);
			if (array_search($entryIdentifier, $identifiers) === FALSE) {
				$identifiers[] = $entryIdentifier;
				zend_disk_cache_store($this->identifierPrefix . 'tag_' . $tag, $identifiers);
			}

				// Update identifier-to-tag index
			$existingTags = $this->findTagsByIdentifier($entryIdentifier);
			if (array_search($entryIdentifier, $existingTags) === false) {
				zend_disk_cache_store($this->identifierPrefix . 'ident_' . $entryIdentifier, array_merge($existingTags, $tags));
			}

		}
	}

	/**
	 * Removes association of the identifier with the given tags
	 *
	 * @param string $entryIdentifier
	 * @param array $tags
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @author Dmitry Dulepov <dmitry.@typo3.org>
	 * @author Benoit Norrin <benoit@norrin.fr>
	 */
	protected function removeIdentifierFromAllTags($entryIdentifier) {
			// Get tags for this identifier
		$tags = $this->findTagsByIdentifier($entryIdentifier);
			// Deassociate tags with this identifier
		foreach ($tags as $tag) {
			$identifiers = $this->findIdentifiersByTag($tag);
				// Formally array_search() below should never return false due to
				// the behavior of findTagsByIdentifier(). But if reverse index is
				// corrupted, we still can get 'false' from array_search(). This is
				// not a problem because we are removing this identifier from
				// anywhere.
			if (($key = array_search($entryIdentifier, $identifiers)) !== FALSE) {
				unset($identifiers[$key]);
				if (count($identifiers)) {
					zend_disk_cache_store($this->identifierPrefix . 'tag_' . $tag, $identifiers);
				} else {
					zend_disk_cache_delete($this->identifierPrefix . 'tag_' . $tag);
				}
			}
		}
			// Clear reverse tag index for this identifier
		zend_disk_cache_delete($this->identifierPrefix . 'ident_' . $entryIdentifier);
	}

	/**
	 * Does nothing, as ZDC does GC itself
	 *
	 * @return void
	 */
	public function collectGarbage() {

	}
}

?>