<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          'qe@SyA[?V+JrBicwoV%]jK%fC>&Y}.{lI2H_/4er/zvxzId(7d*,qQ]aG3<Qz{S%' );
define( 'SECURE_AUTH_KEY',   '0VaJRQ#763lLf}UX1)_LkY|B9-P?76=:E*:Gzpq^3|;HNU#SWO5%GUu/-kxO:E]u' );
define( 'LOGGED_IN_KEY',     'lEsHbMr!KqQTX1ggnE%0c]|cc(j?WX|SYvJ<v/aBIjWHnUYsiUmOnGIWJ4kFJs-F' );
define( 'NONCE_KEY',         '~UIbDU?t9-#<YJ:/AO/|^<(:k]60vioAAXKO21100[J_G(LOI-EgIGa*up8..I7(' );
define( 'AUTH_SALT',         '%S#lkA2fGbbn<5#7ygC`{mN&o@?S%C_`x~sN|C|zcaf*9fsQ?tZGNVd^Nobd{ZJi' );
define( 'SECURE_AUTH_SALT',  '![1[eagkGUiZo/aRK[~[&a<Nr)l|}k5@,R%W/D8Za0Z;M9e#VKxe8)tb|sP]BTy)' );
define( 'LOGGED_IN_SALT',    'Vz`aC9UtHvlVo|E_X`GSYo^%2f)!owy6/Yyb/J88V0M)X0GOgul.l&w{,S(]L h;' );
define( 'NONCE_SALT',        'O~2/}:B~Tvo!6J4vpo^Hv*gl]:v3F%f>[t2_qN{k=[nG9v]#kOa,B)[m??D4b=v(' );
define( 'WP_CACHE_KEY_SALT', '.i{I,[ V5jS/evfw&7x~X5^F|8Qg]viv .q&7Kya9i11zm+(J+6K?He$UNE{VP+_' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
