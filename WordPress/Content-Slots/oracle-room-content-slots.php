/**
 * Oracle Room Content Slots
 *
 * Lightweight editable-copy infrastructure for bespoke Elementor HTML objects.
 * Canonical component structure/default copy lives in GitHub.
 * WordPress stores editor overrides only.
 *
 * Code Snippets FREE: add as one PHP snippet and run everywhere.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

define( 'OR_CONTENT_SLOTS_META', '_or_content_slots' );
define( 'OR_CONTENT_MANIFEST_META', '_or_content_manifest' );

function or_content_slots_supported_post_types() {
    return array( 'page', 'post' );
}

function or_content_slots_supported( $post_id ) {
    return in_array( get_post_type( $post_id ), or_content_slots_supported_post_types(), true );
}

function or_content_slots_decode_elementor( $post_id ) {
    $raw = get_post_meta( $post_id, '_elementor_data', true );

    if ( empty( $raw ) ) {
        return array();
    }

    $decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

    return is_array( $decoded ) ? $decoded : array();
}

function or_content_slots_discover( $post_id ) {
    $decoded = or_content_slots_decode_elementor( $post_id );

    if ( ! $decoded ) {
        return array();
    }

    $manifest = array();

    $walk = function( $nodes ) use ( &$walk, &$manifest ) {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
                foreach ( $node['settings'] as $value ) {
                    if ( ! is_string( $value ) || false === strpos( $value, 'data-or-content-slot' ) ) {
                        continue;
                    }

                    $pattern = '/<([a-z][a-z0-9]*)\\b([^>]*\\bdata-or-content-slot\\s*=\\s*["\\\']([^"\\\']+)["\\\'][^>]*)>(.*?)<\\/\\1>/is';

                    if ( ! preg_match_all( $pattern, $value, $matches, PREG_SET_ORDER ) ) {
                        continue;
                    }

                    foreach ( $matches as $match ) {
                        $attrs = $match[2];
                        $slot  = sanitize_key( $match[3] );

                        if ( ! $slot ) {
                            continue;
                        }

                        $type  = 'text';
                        $label = ucwords( str_replace( array( '-', '_' ), ' ', $slot ) );

                        if ( preg_match( '/\\bdata-or-content-type\\s*=\\s*["\\\']([^"\\\']+)["\\\']/i', $attrs, $m ) ) {
                            $candidate = sanitize_key( $m[1] );
                            if ( in_array( $candidate, array( 'text', 'textarea', 'richtext' ), true ) ) {
                                $type = $candidate;
                            }
                        }

                        if ( preg_match( '/\\bdata-or-content-label\\s*=\\s*["\\\']([^"\\\']+)["\\\']/i', $attrs, $m ) ) {
                            $label = sanitize_text_field( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
                        }

                        $default = 'richtext' === $type
                            ? trim( wp_kses_post( $match[4] ) )
                            : trim( wp_strip_all_tags( html_entity_decode( $match[4], ENT_QUOTES, 'UTF-8' ), true ) );

                        $manifest[ $slot ] = array(
                            'type'    => $type,
                            'label'   => $label,
                            'default' => $default,
                        );
                    }
                }
            }

            if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
                $walk( $node['elements'] );
            }
        }
    };

    $walk( $decoded );

    return $manifest;
}

function or_content_slots_refresh( $post_id ) {
    if ( ! or_content_slots_supported( $post_id ) ) {
        return;
    }

    $manifest = or_content_slots_discover( $post_id );

    if ( $manifest ) {
        update_post_meta( $post_id, OR_CONTENT_MANIFEST_META, $manifest );
        return;
    }

    delete_post_meta( $post_id, OR_CONTENT_MANIFEST_META );
    delete_post_meta( $post_id, OR_CONTENT_SLOTS_META );
}

function or_content_slots_manifest( $post_id ) {
    $manifest = get_post_meta( $post_id, OR_CONTENT_MANIFEST_META, true );

    if ( ! is_array( $manifest ) || ! $manifest ) {
        $manifest = or_content_slots_discover( $post_id );
        if ( $manifest ) {
            update_post_meta( $post_id, OR_CONTENT_MANIFEST_META, $manifest );
        }
    }

    return is_array( $manifest ) ? $manifest : array();
}

add_action( 'save_post', function( $post_id ) {
    if ( ! or_content_slots_supported( $post_id ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( current_user_can( 'edit_post', $post_id ) ) {
        or_content_slots_refresh( $post_id );
    }
}, 31 );

add_action( 'elementor/editor/after_save', function( $post_id ) {
    or_content_slots_refresh( absint( $post_id ) );
}, 31 );

add_action( 'add_meta_boxes', function( $post_type, $post ) {
    if ( ! $post || ! in_array( $post_type, or_content_slots_supported_post_types(), true ) ) {
        return;
    }

    if ( ! or_content_slots_manifest( $post->ID ) ) {
        return;
    }

    add_meta_box(
        'or-content-slots',
        'Oracle Room Content',
        'or_content_slots_metabox',
        $post_type,
        'normal',
        'high'
    );
}, 10, 2 );

function or_content_slots_metabox( $post ) {
    $manifest = or_content_slots_manifest( $post->ID );
    $values   = get_post_meta( $post->ID, OR_CONTENT_SLOTS_META, true );
    $values   = is_array( $values ) ? $values : array();

    wp_nonce_field( 'or_content_slots_save', 'or_content_slots_nonce' );

    echo '<p style="margin-top:0;color:#646970">Edit the exposed copy without touching the component code. Clear a field to return to its coded GitHub default.</p>';

    foreach ( $manifest as $slot => $definition ) {
        $type    = isset( $definition['type'] ) ? $definition['type'] : 'text';
        $label   = isset( $definition['label'] ) ? $definition['label'] : $slot;
        $default = isset( $definition['default'] ) ? $definition['default'] : '';
        $value   = isset( $values[ $slot ]['value'] ) ? $values[ $slot ]['value'] : '';

        echo '<div style="margin:0 0 20px">';
        echo '<label style="display:block;font-weight:600;margin-bottom:6px" for="or-content-' . esc_attr( $slot ) . '">' . esc_html( $label ) . '</label>';
        echo '<div style="font-size:12px;color:#646970;margin-bottom:6px">' . esc_html( $slot ) . '</div>';

        if ( 'richtext' === $type ) {
            wp_editor(
                $value,
                'or_content_' . sanitize_key( $slot ),
                array(
                    'textarea_name' => 'or_content_slots[' . esc_attr( $slot ) . '][value]',
                    'textarea_rows' => 7,
                    'media_buttons' => false,
                    'teeny'         => true,
                )
            );
        } elseif ( 'textarea' === $type ) {
            echo '<textarea id="or-content-' . esc_attr( $slot ) . '" name="or_content_slots[' . esc_attr( $slot ) . '][value]" rows="5" style="width:100%">' . esc_textarea( $value ) . '</textarea>';
        } else {
            echo '<input id="or-content-' . esc_attr( $slot ) . '" type="text" name="or_content_slots[' . esc_attr( $slot ) . '][value]" value="' . esc_attr( $value ) . '" style="width:100%">';
        }

        if ( '' !== $default ) {
            $preview = wp_strip_all_tags( $default );
            if ( mb_strlen( $preview ) > 180 ) {
                $preview = mb_substr( $preview, 0, 177 ) . '…';
            }
            echo '<div style="font-size:12px;color:#646970;margin-top:6px"><strong>Coded default:</strong> ' . esc_html( $preview ) . '</div>';
        }

        echo '</div>';
    }
}

function or_content_slots_clean( $type, $value ) {
    if ( 'richtext' === $type ) {
        return trim( wp_kses_post( $value ) );
    }

    if ( 'textarea' === $type ) {
        return trim( sanitize_textarea_field( $value ) );
    }

    return sanitize_text_field( $value );
}

add_action( 'save_post', function( $post_id ) {
    if ( ! or_content_slots_supported( $post_id ) ) {
        return;
    }

    if ( ! isset( $_POST['or_content_slots_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['or_content_slots_nonce'] ) ), 'or_content_slots_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $manifest = or_content_slots_manifest( $post_id );
    $posted   = isset( $_POST['or_content_slots'] ) && is_array( $_POST['or_content_slots'] )
        ? wp_unslash( $_POST['or_content_slots'] )
        : array();

    $clean = array();

    foreach ( $manifest as $slot => $definition ) {
        $type    = isset( $definition['type'] ) ? $definition['type'] : 'text';
        $default = isset( $definition['default'] ) ? $definition['default'] : '';
        $raw     = isset( $posted[ $slot ]['value'] ) ? $posted[ $slot ]['value'] : '';
        $value   = or_content_slots_clean( $type, $raw );

        if ( '' === $value || $value === or_content_slots_clean( $type, $default ) ) {
            continue;
        }

        $clean[ $slot ] = array(
            'type'  => $type,
            'value' => $value,
        );
    }

    if ( $clean ) {
        update_post_meta( $post_id, OR_CONTENT_SLOTS_META, $clean );
    } else {
        delete_post_meta( $post_id, OR_CONTENT_SLOTS_META );
    }
}, 20 );

function or_content_slots_apply( $html, $post_id ) {
    $manifest = or_content_slots_manifest( $post_id );
    $values   = get_post_meta( $post_id, OR_CONTENT_SLOTS_META, true );
    $values   = is_array( $values ) ? $values : array();

    if ( ! $manifest || ! $values ) {
        return $html;
    }

    foreach ( $manifest as $slot => $definition ) {
        if ( ! isset( $values[ $slot ]['value'] ) ) {
            continue;
        }

        $type  = isset( $definition['type'] ) ? $definition['type'] : 'text';
        $value = $values[ $slot ]['value'];

        if ( 'richtext' === $type ) {
            $rendered = wp_kses_post( $value );
        } elseif ( 'textarea' === $type ) {
            $rendered = nl2br( esc_html( $value ) );
        } else {
            $rendered = esc_html( $value );
        }

        $quoted = preg_quote( $slot, '/' );
        $pattern = '/(<([a-z][a-z0-9]*)\\b[^>]*\\bdata-or-content-slot\\s*=\\s*["\\\']' . $quoted . '["\\\'][^>]*>)(.*?)(<\\/\\2>)/is';

        $html = preg_replace_callback(
            $pattern,
            function( $match ) use ( $rendered ) {
                return $match[1] . $rendered . $match[4];
            },
            $html
        );
    }

    return $html;
}

add_filter( 'the_content', function( $content ) {
    if ( is_admin() || ! is_singular( or_content_slots_supported_post_types() ) ) {
        return $content;
    }

    $post_id = get_the_ID();

    return $post_id ? or_content_slots_apply( $content, $post_id ) : $content;
}, 99 );
