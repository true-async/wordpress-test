<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'trueasync' );

/** Database username */
define( 'DB_USER', 'trueasync' );

/** Database password */
define( 'DB_PASSWORD', 'trueasync' );

/** Database hostname */
define( 'DB_HOST', 'localhost:/var/run/mysqld/mysqld.sock' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'kSP@; ~bGBi *sc:Y5XgrMh.`ql+/dK}Y8f)Y,%@>XP]4k|^@qq_,#ZN7iHj I!i' );
define( 'SECURE_AUTH_KEY',  'r)i+]Vfn=B52CzIFXw< 2nXKIr0;CersvY<31U3PX<G6*CAvgT9jD(xl(hf&4u^s' );
define( 'LOGGED_IN_KEY',    '%4e9sjCTcujL5VmFHQL`>5BgDU@.6b>=3qlT/]O0gA)0}qZDu`5LtDnVf_;<<z%2' );
define( 'NONCE_KEY',        '9G>QLka<ZouLySa_eIbGH$RN)Gar|ZI4X=@gkaT:yc,42nXr2n8N9B@9yEefgEQf' );
define( 'AUTH_SALT',        '!@#Sy>vZQ?F:hv4#WnJAN })UBrOf4>xQ%^w2=gYO9fu7C3mJ}?JEFi(q64(WN#r' );
define( 'SECURE_AUTH_SALT', 'w8V9,IkK)Rq|pzlz]gp0IT+!K,vD*e{uOKJk:1KVwV07B-:sMS3_QHUZjjp3@fd6' );
define( 'LOGGED_IN_SALT',   'tk6gS|K_)Y5[^5.Y[(38Yh,%]z,c4G,I`,t;FVtO<4QNazcu-3cPJzm#4kMEi/h.' );
define( 'NONCE_SALT',       'c+<T;/ =#~8>_],g31yD&2#e-k]euvr!-Opx`<t^Is/xS,qYx8*-jf$mQH5DVQyn' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
