SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `facebook_data` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `source_id` int(10) unsigned NOT NULL,
  `status_id` varchar(45) NOT NULL,
  `status` text,
  `source` varchar(256) NOT NULL,
  `created_at` varchar(45) NOT NULL,
  PRIMARY KEY  USING BTREE (`id`),
  UNIQUE KEY `DUPLICATES` USING BTREE (`source_id`, `status_id`),
  FULLTEXT KEY `SEARCH` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;
