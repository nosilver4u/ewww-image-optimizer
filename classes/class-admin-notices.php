<?php
/**
 * Implements basic page parsing functions.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML element and attribute parsing, replacing, etc.
 */
final class Admin_Notices extends Base {

    /**
	 * Register the loader for the admin notices.
	 */
    public function __construct() {
		parent::__construct();

        \add_action( 'load-index.php', array( $this, 'load_notices' ) );
        \add_action( 'load-upload.php', array( $this, 'load_notices' ), 9 );
		\add_action( 'load-media-new.php', array( $this, 'load_notices' ) );
		\add_action( 'load-media_page_ewww-image-optimizer-bulk', array( $this, 'load_notices' ) );
		\add_action( 'load-plugins.php', array( $this, 'load_notices' ) );
    }

    /**
	 * Register the admin notices, and add the actions to display them.
	 */
    public function load_notices() {
        \add_action( 'network_admin_notices', array( $this, 'display_notices' ) );
        \add_action( 'admin_notices', array( $this, 'display_notices' ) );
    }
    
    /**
     * Hook to display notices on the admin pages.
     */
    public function display_notices() {
        $admin_permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
		if ( current_user_can( $admin_permissions ) ) {
			$this->test_mode_notice();
			$this->debug_mode_notice();
            $this->notice_utils();
            $this->notice_os();
            $this->notice_hosting_requires_api();
            if ( is_network_admin() ) {
                // If we are on the network admin, display network notices.
                \do_action( 'ewwwio_network_admin_notices' );
            } else {
                // Otherwise, display regular admin notices.
                \do_action( 'ewwwio_admin_notices' );
            }
        }
    }

    /**
	 * Display admin notice for test mode.
	 */
	protected function test_mode_notice() {
		if ( ! $this->get_option( 'ewww_image_optimizer_test_mode' ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'EWWW Image Optimizer is currently in Test Mode. Please be sure to disable Test Mode when you are done troubleshooting.', 'ewww-image-optimizer' ); ?>
				<a class='button button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_disable_test_mode' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
					<?php esc_html_e( 'Disable Test Mode', 'ewww-image-optimizer' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

    /**
     * Display a notice that debugging mode is enabled.
     */
    protected function debug_mode_notice() {
	    if ( ! $this->get_option( 'ewww_image_optimizer_debug' ) ) {
	    	return;
        }
        ?>
        <div id="ewww-image-optimizer-notice-debug" class="notice notice-info">
            <p>
                <?php esc_html_e( 'Debug mode is enabled in the EWWW Image Optimizer settings. Please be sure to turn Debugging off when you are done troubleshooting.', 'ewww-image-optimizer' ); ?>
                <a class='button button-secondary' href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_disable_debugging' ), 'ewww_image_optimizer_options-options' ) ); ?>'>
                    <?php esc_html_e( 'Disable Debugging', 'ewww-image-optimizer' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

	/**
	 * Disables the test mode option.
	 */
	public function disable_test_mode() {
		if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'ewww_image_optimizer_options-options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
		}
		update_option( 'ewww_image_optimizer_test_mode', false );
		update_site_option( 'ewww_image_optimizer_test_mode', false );
		$sendback = wp_get_referer();
		wp_safe_redirect( $sendback );
		exit;
	}

    /**
	 * Checks for exec() and availability of local optimizers, then displays an error if needed.
	 *
	 * @param string $quiet Optional. Use 'quiet' to suppress output.
	 */
	public function notice_utils( $quiet = null ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ewwwio()->cloud_mode ) {
            return;
        }
        // Check if exec is disabled.
        if ( ! ewwwio()->local->exec_check() ) {
			// Don't bother if we're in quiet mode, or they already dismissed the notice.
			if ( 'quiet' !== $quiet && ! $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
				\ob_start();
				// Display a warning if exec() is disabled, can't run local tools without it.
				if ( \ewww_image_optimizer_easy_active() ) {
					echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-info is-dismissible'><p>";
					\esc_html_e( 'Compression of local images cannot be done on your site without an API key. Since Easy IO is already automatically optimizing your site, you may dismiss this notice unless you need to save storage space.', 'ewww-image-optimizer' );
				} else {
					echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>";
					\printf(
						/* translators: %s: link to 'start your premium trial' */
						\esc_html__( 'Your web server does not meet the requirements for free server-based compression with EWWW Image Optimizer. You may %s for 5x more compression, PNG/GIF/PDF compression, and more. Otherwise, continue with free cloud-based JPG compression.', 'ewww-image-optimizer' ),
						"<a href='https://ewww.io/plans/'>" . \esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>'
					);
				}
				\ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
				echo '</p></div>';
				$this->display_exec_dismiss_script();
				if (
					\ewww_image_optimizer_easy_active() &&
					! $this->get_option( 'ewww_image_optimizer_ludicrous_mode' )
				) {
					\ob_end_clean();
				} else {
					\ob_end_flush();
				}
				$this->debug_message( 'exec disabled, alerting user' );
			}
			return;
		}

		$tools   = ewwwio()->local->check_all_tools();
		$missing = array();
		// Go through each of the required tools.
		foreach ( $tools as $tool => $info ) {
			// If a tool is needed, but wasn't found, add it to the $missing so we can display that info to the user.
			if ( $info['enabled'] && empty( $info['path'] ) ) {
				if ( 'cwebp' === $tool && ( $this->imagick_supports_webp() || $this->gd_supports_webp() ) ) {
					continue;
				}
				ewwwio()->local->tools_missing = true;
				$missing[]                     = $tool;
			}
		}
		// If there is a message, display the warning.
		if ( ! empty( $missing ) && 'quiet' !== $quiet ) {
			if ( ! \is_dir( $this->content_dir ) ) {
				$this->tool_folder_notice();
			} elseif ( ! \is_writable( $this->content_dir ) || ! is_readable( $this->content_dir ) ) {
				$this->tool_folder_permissions_notice();
			} elseif ( ! \is_executable( $this->content_dir ) && PHP_OS !== 'WINNT' ) {
				$this->tool_folder_permissions_notice();
			}
			if ( \in_array( 'pngout', $missing, true ) ) {
				// Display a separate notice for pngout with an install option, and then suppress it from the latter notice.
				$key = \array_search( 'pngout', $missing, true );
				if ( false !== $key ) {
					unset( $missing[ $key ] );
				}
				echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-warning'><p>" .
				\sprintf(
					/* translators: 1: automatically (link) 2: manually (link) */
					\esc_html__( 'EWWW Image Optimizer is missing pngout. Install %1$s or %2$s.', 'ewww-image-optimizer' ),
					"<a href='" . \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=ewww_image_optimizer_install_pngout' ), 'ewww_image_optimizer_options-options' ) ) . "'>" . \esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a>',
					'<a href="https://docs.ewww.io/article/13-installing-pngout" data-beacon-article="5854531bc697912ffd6c1afa">' . \esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>'
				) .
				'</p></div>';
			}
			if ( \in_array( 'svgcleaner', $missing, true ) ) {
				$key = array_search( 'svgcleaner', $missing, true );
				if ( false !== $key ) {
					unset( $missing[ $key ] );
				}
				echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-warning'><p>" .
				\sprintf(
					/* translators: 1: automatically (link) 2: manually (link) */
					\esc_html__( 'EWWW Image Optimizer is missing svgleaner. Install %1$s or %2$s.', 'ewww-image-optimizer' ),
					"<a href='" . \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=ewww_image_optimizer_install_svgcleaner' ), 'ewww_image_optimizer_options-options' ) ) . "'>" . \esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a>',
					'<a href="https://docs.ewww.io/article/95-installing-svgcleaner" data-beacon-article="5f7921c9cff47e001a58adbc">' . \esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>'
				) .
				'</p></div>';
			}
			if ( ! empty( $missing ) && ! $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
				$dismissible = false;
				// If all the tools are missing, make it dismissible.
				if (
					\in_array( 'jpegtran', $missing, true ) &&
					\in_array( 'optipng', $missing, true ) &&
					\in_array( 'gifsicle', $missing, true )
				) {
					$dismissible = true;
				}
				// If they are missing tools, but not jpegtran, make it dismissible, since they can effectively do locally what we would offer in free-cloud mode.
				if ( ! \in_array( 'jpegtran', $missing, true ) ) {
					$dismissible = true;
				}
				// Expand the missing utilities list for use in the error message.
				$msg = \implode( ', ', $missing );
				echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-warning" . ( $dismissible ? ' is-dismissible' : '' ) . "'><p>" .
				\sprintf(
					/* translators: 1: comma-separated list of missing tools 2: Installation Instructions (link) */
					\esc_html__( 'EWWW Image Optimizer uses open-source tools to enable free mode, but your server is missing these: %1$s. Please install via the %2$s to continue in free mode.', 'ewww-image-optimizer' ),
					\esc_html( $msg ),
					"<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something' data-beacon-article='585371e3c697912ffd6c0ba1' target='_blank'>" . \esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>'
				) .
				'</p></div>';
				?>
	<script>
		jQuery(document).on('click', '#ewww-image-optimizer-warning-opt-missing .notice-dismiss', function() {
			var ewww_dismiss_exec_data = {
				action: 'ewww_dismiss_exec_notice',
			};
			jQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {
				if (response) {
					console.log(response);
				}
			});
		});
	</script>
				<?php
			}
		}
	}

    /**
	 * Outputs the script to dismiss the 'exec' notice.
	 */
	public function display_exec_dismiss_script() {
		?>
		<script>
			jQuery(document).on('click', '#ewww-image-optimizer-warning-exec .notice-dismiss', function() {
				var ewww_dismiss_exec_data = {
					action: 'ewww_dismiss_exec_notice',
					_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'ewww-image-optimizer-notice' ) ); ?>,
				};
				jQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {
					if (response) {
						console.log(response);
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Alert the user when the tool folder could not be created.
	 */
	public function tool_folder_notice() {
		echo "<div id='ewww-image-optimizer-warning-tool-folder-create' class='notice notice-error'><p><strong>" . \esc_html__( 'EWWW Image Optimizer could not create the tool folder', 'ewww-image-optimizer' ) . ': ' . \esc_html( $this->content_dir ) . '.</strong> ' . \esc_html__( 'Please adjust permissions or create the folder', 'ewww-image-optimizer' ) . '.</p></div>';
	}

	/**
	 * Alert the user when permissions on the tool folder are insufficient.
	 */
	public function tool_folder_permissions_notice() {
		echo "<div id='ewww-image-optimizer-warning-tool-folder-permissions' class='notice notice-error'><p><strong>" .
			/* translators: %s: Folder location where executables should be installed */
			\sprintf( \esc_html__( 'EWWW Image Optimizer could not install tools in %s', 'ewww-image-optimizer' ), \esc_html( $this->content_dir ) ) . '.</strong> ' .
			\esc_html__( 'Please adjust permissions on the folder. If you have installed the tools elsewhere, use the override to skip the bundled tools.', 'ewww-image-optimizer' ) . ' ' .
			/* translators: s: Installation Instructions (link) */
			\sprintf( \esc_html__( 'For more details, see the %s.', 'ewww-image-optimizer' ), "<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something'>" . \esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Let the user know the plugin requires API/ExactDN to operate at their webhost.
	 */
	public function notice_hosting_requires_api() {
		if ( ! ewwwio()->local->hosting_requires_api() ) {
			return;
        }
        if (
			$this->get_option( 'ewww_image_optimizer_cloud_key' ) ||
			\ewww_image_optimizer_easy_active()
		) {
            return;
        }
        if ( \defined( 'WPCOMSH_VERSION' ) ) {
			$webhost = 'WordPress.com';
		} elseif ( ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
			$webhost = 'Pantheon';
		} elseif ( \defined( 'WPE_PLUGIN_VERSION' ) ) {
			$webhost = 'WP Engine';
		} elseif ( \defined( 'FLYWHEEL_CONFIG_DIR' ) ) {
			$webhost = 'Flywheel';
		} elseif ( \defined( 'KINSTAMU_VERSION' ) ) {
			$webhost = 'Kinsta';
		} elseif ( \defined( 'WPNET_INIT_PLUGIN_VERSION' ) ) {
			$webhost = 'WP NET';
		} else {
			return;
		}
		if ( $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
			return;
		}
		echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>" .
			/* translators: %s: Name of a web host, like WordPress.com or Pantheon. */
			\sprintf( \esc_html__( 'EWWW Image Optimizer cannot use server-based optimization on %s sites. Activate our premium service for 5x more compression, PNG/GIF/PDF compression, and image backups.', 'ewww-image-optimizer' ), \esc_html( $webhost ) ) .
			'<br><strong>' .
			/* translators: %s: link to 'start your free trial' */
			\sprintf( \esc_html__( 'Dismiss this notice to continue with free cloud-based JPG compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . \esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' );
		\ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
		echo '</strong></p></div>';
		$this->display_exec_dismiss_script();
	}

	/**
	 * Tells the user they are on an unsupported operating system.
	 */
	public function notice_os() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
			return;
		}
        if ( ewwwio()->local->os_supported() ) {
            return;
        }
		// If they are already using our services, or haven't gone through the wizard, exit now!
		if (
			$this->get_option( 'ewww_image_optimizer_cloud_key' ) ||
			\ewww_image_optimizer_easy_active()
		) {
			return;
		}
		echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>" .
			\esc_html__( 'Free server-based compression with EWWW Image Optimizer is only supported on Linux, FreeBSD, Mac OSX, and Windows.', 'ewww-image-optimizer' ) .
			'<br><strong>' .
			/* translators: %s: link to 'start your free trial' */
			\sprintf( \esc_html__( 'Dismiss this notice to continue with free cloud-based JPG compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . \esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' );
		\ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
		echo '</strong></p></div>';
		$this->display_exec_dismiss_script();
	}
}
