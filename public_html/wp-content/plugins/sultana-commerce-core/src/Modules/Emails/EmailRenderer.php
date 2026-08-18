<?php

namespace Sultana\CommerceCore\Modules\Emails;

use Sultana\CommerceCore\Core\StoreBranding;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailRenderer
{
    /**
     * Renders the shared HTML email shell.
     *
     * The content_html and eyebrow_html values must be built by plugin code with
     * their own escaping. The renderer escapes scalar fields and provides the
     * shared branding, wrapper, card, CTA and footer.
     */
    public static function render( array $args ): string
    {
        $title        = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
        $eyebrow      = sanitize_text_field( (string) ( $args['eyebrow'] ?? '' ) );
        $eyebrow_html = (string) ( $args['eyebrow_html'] ?? '' );
        $content_html = (string) ( $args['content_html'] ?? '' );
        $after_button_html = (string) ( $args['after_button_html'] ?? '' );
        $footer_text  = sanitize_text_field( (string) ( $args['footer_text'] ?? self::default_footer_text() ) );
        $button       = is_array( $args['button'] ?? null ) ? $args['button'] : [];
        $button_label = sanitize_text_field( (string) ( $button['label'] ?? '' ) );
        $button_url   = esc_url( (string) ( $button['url'] ?? '' ) );
        $button_align = in_array( (string) ( $button['align'] ?? 'right' ), [ 'left', 'center', 'right' ], true ) ? (string) $button['align'] : 'right';
        $brand_color  = StoreBranding::get_primary_color();
        $site_name    = StoreBranding::get_name();

        ob_start();
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( $title ); ?></title>
        </head>
        <body style="margin:0;padding:0;background:#fff7fb;color:#101122;font-family:Arial,Helvetica,sans-serif;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#fff7fb;margin:0;padding:0 0 26px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;margin:0 auto;">
                            <tr>
                                <td style="background:#ffffff;border:1px solid #f2d5e2;border-radius:5px;padding:24px 28px 28px;box-shadow:0 16px 36px rgba(47,54,64,0.08);">
                                    <div style="margin:0 0 22px;">
                                        <?php echo self::logo_html( $site_name, $brand_color ); ?>
                                    </div>

                                    <?php if ( '' !== $eyebrow_html ) : ?>
                                        <?php echo $eyebrow_html; ?>
                                    <?php elseif ( '' !== $eyebrow ) : ?>
                                        <p style="margin:0 0 8px;color:<?php echo esc_attr( $brand_color ); ?>;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">
                                            <?php echo esc_html( $eyebrow ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ( '' !== $title ) : ?>
                                        <h1 style="margin:0 0 12px;color:#101122;font-size:28px;line-height:1.18;font-weight:700;">
                                            <?php echo esc_html( $title ); ?>
                                        </h1>
                                    <?php endif; ?>

                                    <?php echo $content_html; ?>

                                    <?php if ( '' !== $button_label && '' !== $button_url ) : ?>
                                        <div style="text-align:<?php echo esc_attr( $button_align ); ?>;margin:0 0 <?php echo '' !== $after_button_html ? '22px' : '0'; ?>;">
                                            <a href="<?php echo esc_url( $button_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( $brand_color ); ?>;color:#ffffff;text-decoration:none;border-radius:8px;padding:14px 24px;font-size:15px;font-weight:700;">
                                                <?php echo esc_html( $button_label ); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php echo $after_button_html; ?>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:18px 10px 0;color:#8a7b86;font-size:12px;line-height:1.5;">
                                    <?php echo esc_html( $footer_text ); ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php

        return (string) ob_get_clean();
    }

    public static function html_headers( $headers = [] ): array
    {
        $headers = is_array( $headers ) ? $headers : array_filter( array_map( 'trim', explode( "\n", (string) $headers ) ) );
        $headers = array_filter(
            $headers,
            static function ( $header ): bool {
                return 0 !== stripos( (string) $header, 'Content-Type:' );
            }
        );

        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        return array_values( $headers );
    }

    public static function default_footer_text(): string
    {
        return sprintf(
            /* translators: %s: store name. */
            __( 'Este correo fue enviado por %s.', 'sultana-commerce-core' ),
            StoreBranding::get_name()
        );
    }

    private static function logo_html( string $site_name, string $brand_color ): string
    {
        $logo_url = StoreBranding::get_logo_url();

        if ( '' === $logo_url ) {
            return sprintf(
                '<strong style="display:block;color:%s;font-size:20px;line-height:1.2;">%s</strong>',
                esc_attr( $brand_color ),
                esc_html( $site_name )
            );
        }

        return sprintf(
            '<img src="%s" width="96" alt="%s" style="display:block;width:96px;max-width:96px;height:auto;border:0;background:transparent;">',
            esc_url( $logo_url ),
            esc_attr( $site_name )
        );
    }
}
