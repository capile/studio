<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio\Model;

class Migration
{
    public static $tables=[
        'studio_entries' => [
            'tdz_entries' => [
                'sql' => "insert into studio_entries (id, title, summary, link, source, format, language, type, master, published, version, created, updated, expired) select id, convert(title using 'utf8'), convert(summary using 'utf8'), convert(link using 'utf8'), convert(source using 'utf8'), format, language, type, master, published, version, created, updated, expired from tdz_entries",
            ],
        ],
        'studio_entries_version' => [
            'tdz_entries_version' => [
                'sql' => "insert into studio_entries_version (id, title, summary, link, source, format, language, type, master, published, version, created, updated, expired) select id, convert(title using 'utf8'), convert(summary using 'utf8'), convert(link using 'utf8'), convert(source using 'utf8'), format, language, type, master, published, version, created, updated, expired from tdz_entries_version group by id, version",
            ],
        ],
        'studio_contents' => [
            'tdz_contents' => [
                'sql' => "insert into studio_contents (id, entry, slot, content_type, source, attributes, content, position, show_at, hide_at, published, version, created, updated, expired) select id, entry, slot, content_type, convert(source using 'utf8'), convert(attributes using 'utf8'), convert(content using 'utf8'), position, show_at, hide_at, published, version, created, updated, expired from tdz_contents",
            ],
        ],
        'studio_contents_version' => [
            'tdz_contents_version' => [
                'sql' => "insert into studio_contents_version (id, entry, slot, content_type, source, attributes, content, position, show_at, hide_at, published, version, created, updated, expired) select id, entry, slot, content_type, convert(source using 'utf8'), convert(attributes using 'utf8'), convert(content using 'utf8'), position, show_at, hide_at, published, version, created, updated, expired from tdz_contents_version group by id, version",
            ],
        ],
        'studio_contents_display' => [
            'tdz_contents_display' => [
                'sql' => 'insert into studio_contents_display(content, link, version, display, created, updated, expired) select * from tdz_contents_display group by content, link',
            ],
        ],
        'studio_contents_display_version' => [
            'tdz_contents_display_version' => [
                'sql' => 'insert into studio_contents_display_version(content, link, version, display, created, updated, expired) select * from tdz_contents_display_version group by content, link, version',
            ],
        ],
        'studio_credentials' => [
            'tdz_credentials' => [
                'sql' => 'insert into studio_credentials (userid,groupid,created,updated,expired) select * from tdz_credentials group by user, groupid',
            ],
        ],
        'studio_groups' => [
            'tdz_groups' => [
                'sql' => 'insert into studio_groups (id,name,description,created,updated,expired) select * from tdz_groups group by id',
            ],
        ],
        'studio_permissions' => [
            'tdz_permissions' => [
                'sql' => 'insert into studio_permissions (id,entry,role,credentials,version,created,updated,expired) select * from tdz_permissions group by id'
            ],
        ],
        'studio_relations' => [
            'tdz_relations' => [
                'sql' => 'insert into studio_relations(id,entry,parent,position,version,created,updated,expired) select * from tdz_relations group by id',
            ],
        ],
        'studio_tags' => [
            'tdz_tags' => [
                'sql' => "insert into studio_tags(id,entry,tag,slug,version,created,updated,expired) select id,entry,convert(tag using 'utf8'),slug,version,created,updated,expired from tdz_tags group by id",
            ],
        ],
        'studio_users' => [
            'tdz_users' => [
                'sql' => 'insert into studio_users(id,username,name,password,email,details,accessed,created,updated,expired) select * from tdz_users group by id',
            ],
        ],
    ];
}