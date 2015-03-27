ALTER TABLE `config` ADD `filters` VARCHAR( 255 ) NOT NULL ;
UPDATE `config` SET `filters` = 'volnorm=1';

