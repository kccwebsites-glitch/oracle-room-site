/**
 * Oracle Room Content Slots
 *
 * Lightweight editable-copy infrastructure for bespoke Elementor HTML objects.
 * Canonical component structure/default copy lives in GitHub.
 * WordPress stores editor overrides only.
 *
 * Code Snippets FREE: add as one PHP snippet and run everywhere.
 * Version: 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

define( 'OR_CONTENT_SLOTS_META', '_or_content_slots' );
define( 'OR_CONTENT_MANIFEST_META', '_or_content_manifest' );
define( 'OR_CONTENT_CHANGE_LOG_META', '_or_content_change_log' );

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

function or_content_slots_append_change_log( $post_id, $entries ) {
    if ( ! $entries || ! is_array( $entries ) ) {
        return;
    }

    $log = get_post_meta( $post_id, OR_CONTENT_CHANGE_LOG_META, true );
    $log = is_array( $log ) ? $log : array();

    foreach ( $entries as $entry ) {
        $log[] = $entry;
    }

    if ( count( $log ) > 100 ) {
        $log = array_slice( $log, -100 );
    }

    update_post_meta( $post_id, OR_CONTENT_CHANGE_LOG_META, $log );
}

function or_content_slots_prune_synced_overrides( $post_id, $manifest ) {
    $values = get_post_meta( $post_id, OR_CONTENT_SLOTS_META, true );
    $values = is_array( $values ) ? $values : array();

    if ( ! $values ) {
        return;
    }

    $changed = false;

    foreach ( $values as $slot => $stored ) {
        if ( ! isset( $manifest[ $slot ] ) || ! is_array( $stored ) || ! array_key_exists( 'value', $stored ) ) {
            if ( ! isset( $manifest[ $slot ] ) ) {
                unset( $values[ $slot ] );
                $changed = true;
            }
            continue;
        }

        $type    = isset( $manifest[ $slot ]['type'] ) ? $manifest[ $slot ]['type'] : 'text';
        $default = isset( $manifest[ $slot ]['default'] ) ? $manifest[ $slot ]['default'] : '';
        $saved   = or_content_slots_clean( $type, $stored['value'] );
        $coded   = or_content_slots_clean( $type, $default );

        if ( $saved === $coded ) {
            unset( $values[ $slot ] );
            $changed = true;
        }
    }

    if ( ! $changed ) {
        return;
    }

    if ( $values ) {
        update_post_meta( $post_id, OR_CONTENT_SLOTS_META, $values );
    } else {
        delete_post_meta( $post_id, OR_CONTENT_SLOTS_META );
    }
}

function or_content_slots_refresh( $post_id ) {
    if ( ! or_content_slots_supported( $post_id ) ) {
        return;
    }

    $manifest = or_content_slots_discover( $post_id );

    if ( $manifest ) {
        update_post_meta( $post_id, OR_CONTENT_MANIFEST_META, $manifest );
        or_content_slots_prune_synced_overrides( $post_id, $manifest );
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


add_action( 'add_meta_boxes', function( $post_type, $post ) {
    if ( ! $post || ! in_array( $post_type, or_content_slots_supported_post_types(), true ) ) {
        return;
    }

    if ( ! or_content_slots_manifest( $post->ID ) ) {
        return;
    }

    add_meta_box(
        'or-content-maintenance',
        'Oracle Room Content Maintenance',
        'or_content_slots_maintenance_metabox',
        $post_type,
        'normal',
        'default'
    );
}, 10, 2 );

function or_content_slots_metabox( $post ) {
    $manifest = or_content_slots_manifest( $post->ID );
    $values   = get_post_meta( $post->ID, OR_CONTENT_SLOTS_META, true );
    $values   = is_array( $values ) ? $values : array();

    wp_nonce_field( 'or_content_slots_save', 'or_content_slots_nonce' );
    ?>
    <style>
        .or-content-list{display:grid;gap:18px;margin-top:14px}
        .or-content-card{border:1px solid #dcdcde;background:#fff;padding:16px}
        .or-content-card h4{margin:0 0 4px;font-size:14px}
        .or-content-type{margin:0 0 10px;color:#646970;font-size:11px;text-transform:uppercase;letter-spacing:.08em}
        .or-content-status{display:inline-block;margin:0 0 10px;padding:3px 7px;background:#f0f0f1;color:#50575e;font-size:11px}
        .or-content-status.is-custom{background:#e7f5ea;color:#14532d}
        .or-content-card input[type="text"],.or-content-card textarea{width:100%}
        .or-content-card textarea{min-height:110px;resize:vertical}
        .or-content-default{margin:10px 0 0;padding:10px 12px;border-left:3px solid #c3c4c7;background:#f6f7f7;color:#50575e;font-size:12px;line-height:1.5}
        .or-content-default strong{display:block;margin-bottom:4px}
        .or-content-key{margin-top:8px;color:#8c8f94;font-size:11px;font-family:monospace}
    </style>

    <p>These fields come from editable copy slots declared inside the Oracle Room component. Layout and code stay protected.</p>
    <p><strong>Tip:</strong> saving copy that matches the coded default removes the override automatically.</p>

    <div class="or-content-list">
        <?php foreach ( $manifest as $slot => $definition ) :
            $type       = isset( $definition['type'] ) ? $definition['type'] : 'text';
            $label      = isset( $definition['label'] ) ? $definition['label'] : $slot;
            $default    = isset( $definition['default'] ) ? $definition['default'] : '';
            $has_custom = isset( $values[ $slot ] ) && is_array( $values[ $slot ] ) && array_key_exists( 'value', $values[ $slot ] );
            $value      = $has_custom ? $values[ $slot ]['value'] : $default;
            $editor_id  = 'or_content_' . substr( md5( $slot ), 0, 12 );
        ?>
            <div class="or-content-card">
                <h4><?php echo esc_html( $label ); ?></h4>
                <div class="or-content-type"><?php echo esc_html( $type ); ?></div>
                <div class="or-content-status <?php echo $has_custom ? 'is-custom' : ''; ?>">
                    <?php echo $has_custom ? 'Custom copy saved' : 'Using coded default'; ?>
                </div>

                <?php if ( 'richtext' === $type ) : ?>
                    <?php
                    wp_editor(
                        $value,
                        $editor_id,
                        array(
                            'textarea_name' => 'or_content_slots[' . $slot . '][value]',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny'         => true,
                            'quicktags'     => true,
                        )
                    );
                    ?>
                <?php elseif ( 'textarea' === $type ) : ?>
                    <textarea name="or_content_slots[<?php echo esc_attr( $slot ); ?>][value]"><?php echo esc_textarea( $value ); ?></textarea>
                <?php else : ?>
                    <input type="text"
                           name="or_content_slots[<?php echo esc_attr( $slot ); ?>][value]"
                           value="<?php echo esc_attr( $value ); ?>">
                <?php endif; ?>

                <div class="or-content-default">
                    <strong>Coded default</strong>
                    <?php if ( 'richtext' === $type ) : ?>
                        <?php echo wp_kses_post( $default ); ?>
                    <?php else : ?>
                        <?php echo esc_html( $default ); ?>
                    <?php endif; ?>
                </div>
                <div class="or-content-key"><?php echo esc_html( $slot ); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function or_content_slots_maintenance_metabox( $post ) {
    $manifest = or_content_slots_manifest( $post->ID );
    $values   = get_post_meta( $post->ID, OR_CONTENT_SLOTS_META, true );
    $values   = is_array( $values ) ? $values : array();
    $log      = get_post_meta( $post->ID, OR_CONTENT_CHANGE_LOG_META, true );
    $log      = is_array( $log ) ? $log : array();

    $prompt_lines = array(
        'Update the canonical GitHub Oracle Room component(s) for this WordPress content item so the coded defaults incorporate the current approved WordPress Content Slot overrides.',
        '',
        'Repository: kccwebsites-glitch/oracle-room-site',
        'Content item: ' . get_the_title( $post ),
        'WordPress slug: ' . $post->post_name,
        'URL: ' . get_permalink( $post ),
        '',
        'Instructions:',
        '- Fetch the current canonical component files from the oracle-room-site GitHub repository before editing them.',
        '- Locate the component(s) containing the exact data-or-content-slot keys listed below.',
        '- Update only the coded default copy for those Oracle Room Content Slot keys.',
        '- Preserve each data-or-content-slot key exactly.',
        '- Preserve layout, styling, media slots and JavaScript unless a listed copy change genuinely requires otherwise.',
        '- Commit the canonical component update and give me the commit SHA.',
        '- I will then paste/redeploy the updated component in Elementor and save the page; Oracle Room Content Slots should automatically clear WordPress overrides that now match the new coded defaults.',
        '',
        'Outstanding WordPress overrides:',
    );

    if ( $values ) {
        foreach ( $values as $slot => $stored ) {
            if ( ! isset( $manifest[ $slot ] ) || ! is_array( $stored ) || ! array_key_exists( 'value', $stored ) ) {
                continue;
            }

            $label   = isset( $manifest[ $slot ]['label'] ) ? $manifest[ $slot ]['label'] : $slot;
            $default = isset( $manifest[ $slot ]['default'] ) ? $manifest[ $slot ]['default'] : '';
            $value   = $stored['value'];

            $prompt_lines[] = '';
            $prompt_lines[] = 'Slot: ' . $slot;
            $prompt_lines[] = 'Label: ' . $label;
            $prompt_lines[] = 'Coded default: ' . wp_strip_all_tags( $default );
            $prompt_lines[] = 'WordPress override: ' . wp_strip_all_tags( $value );
        }
    } else {
        $prompt_lines[] = 'None. WordPress currently matches the coded defaults.';
    }

    $prompt = implode( "\n", $prompt_lines );
    $recent = array_slice( array_reverse( $log ), 0, 20 );
    ?>
    <style>
        .or-maintenance-summary{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0 14px}
        .or-maintenance-badge{display:inline-block;padding:5px 9px;background:#f0f0f1;color:#50575e;font-weight:600;font-size:12px}
        .or-maintenance-badge.has-overrides{background:#fff3cd;color:#664d03}
        .or-maintenance-prompt{width:100%;min-height:240px;font-family:monospace;font-size:12px;line-height:1.5}
        .or-maintenance-actions{display:flex;align-items:center;gap:10px;margin:10px 0 18px}
        .or-copy-status{color:#2271b1;font-size:12px}
        .or-change-log{width:100%;border-collapse:collapse;margin-top:10px}
        .or-change-log th,.or-change-log td{padding:8px 10px;border:1px solid #dcdcde;vertical-align:top;text-align:left;font-size:12px}
        .or-change-log th{background:#f6f7f7}
        .or-change-log code{font-size:11px}
        .or-change-log-copy{max-width:360px;white-space:pre-wrap;word-break:break-word}
    </style>

    <p>This is the bridge between WordPress copy edits and the canonical GitHub component. WordPress can diverge temporarily; GitHub remains the source of truth.</p>

    <div class="or-maintenance-summary">
        <span class="or-maintenance-badge <?php echo $values ? 'has-overrides' : ''; ?>">
            <?php
            echo $values
                ? esc_html( count( $values ) . ' outstanding content override' . ( 1 === count( $values ) ? '' : 's' ) )
                : 'No outstanding content overrides';
            ?>
        </span>
        <span>After the canonical component is updated and re-saved in Elementor, matching overrides clear automatically.</span>
    </div>

    <h4>Canonical update prompt</h4>
    <p>Copy this into ChatGPT when you want approved WordPress edits folded back into the GitHub component defaults.</p>
    <textarea id="or-maintenance-prompt-<?php echo esc_attr( $post->ID ); ?>" class="or-maintenance-prompt" readonly><?php echo esc_textarea( $prompt ); ?></textarea>
    <div class="or-maintenance-actions">
        <button type="button" class="button button-primary or-copy-maintenance-prompt" data-target="or-maintenance-prompt-<?php echo esc_attr( $post->ID ); ?>">Copy canonical update prompt</button>
        <span class="or-copy-status" aria-live="polite"></span>
    </div>

    <h4>Recent editor change log</h4>
    <?php if ( $recent ) : ?>
        <table class="or-change-log">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Editor</th>
                    <th>Field</th>
                    <th>Previous</th>
                    <th>New</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recent as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( isset( $entry['time_display'] ) ? $entry['time_display'] : '' ); ?></td>
                        <td><?php echo esc_html( isset( $entry['user'] ) ? $entry['user'] : '' ); ?></td>
                        <td><strong><?php echo esc_html( isset( $entry['label'] ) ? $entry['label'] : '' ); ?></strong><br><code><?php echo esc_html( isset( $entry['slot'] ) ? $entry['slot'] : '' ); ?></code></td>
                        <td class="or-change-log-copy"><?php echo esc_html( isset( $entry['from'] ) ? wp_strip_all_tags( $entry['from'] ) : '' ); ?></td>
                        <td class="or-change-log-copy"><?php echo esc_html( isset( $entry['to'] ) ? wp_strip_all_tags( $entry['to'] ) : '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No Oracle Room Content Slot changes have been logged yet.</p>
    <?php endif; ?>

    <script>
    jQuery(function($){
        $('.or-copy-maintenance-prompt').on('click', async function(){
            const button = $(this);
            const target = document.getElementById(button.data('target'));
            const status = button.siblings('.or-copy-status');
            if (!target) return;

            const text = target.value;
            let copied = false;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    copied = true;
                }
            } catch (e) {}

            if (!copied) {
                target.focus();
                target.select();
                try {
                    copied = document.execCommand('copy');
                } catch (e) {}
                if (window.getSelection) window.getSelection().removeAllRanges();
            }

            status.text(copied ? 'Prompt copied.' : 'Select the prompt and copy it manually.');
        });
    });
    </script>
    <?php
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
    $old      = get_post_meta( $post_id, OR_CONTENT_SLOTS_META, true );
    $old      = is_array( $old ) ? $old : array();
    $posted   = isset( $_POST['or_content_slots'] ) && is_array( $_POST['or_content_slots'] )
        ? wp_unslash( $_POST['or_content_slots'] )
        : array();

    $clean   = $old;
    $changes = array();
    $user    = wp_get_current_user();

    foreach ( $manifest as $slot => $definition ) {
        $type    = isset( $definition['type'] ) ? $definition['type'] : 'text';
        $label   = isset( $definition['label'] ) ? $definition['label'] : $slot;
        $default = isset( $definition['default'] ) ? $definition['default'] : '';
        $raw     = isset( $posted[ $slot ]['value'] ) ? $posted[ $slot ]['value'] : '';

        $value   = or_content_slots_clean( $type, $raw );
        $compare = or_content_slots_clean( $type, $default );

        $old_effective = isset( $old[ $slot ] ) && is_array( $old[ $slot ] ) && array_key_exists( 'value', $old[ $slot ] )
            ? or_content_slots_clean( $type, $old[ $slot ]['value'] )
            : $compare;

        if ( '' === $value || $value === $compare ) {
            unset( $clean[ $slot ] );
            $new_effective = $compare;
        } else {
            $clean[ $slot ] = array(
                'type'  => $type,
                'value' => $value,
            );
            $new_effective = $value;
        }

        if ( $old_effective !== $new_effective ) {
            $changes[] = array(
                'timestamp'    => current_time( 'timestamp' ),
                'time_display' => current_time( 'mysql' ),
                'user_id'      => $user && $user->exists() ? $user->ID : 0,
                'user'         => $user && $user->exists() ? $user->display_name : 'Unknown editor',
                'slot'         => $slot,
                'label'        => $label,
                'type'         => $type,
                'from'         => $old_effective,
                'to'           => $new_effective,
                'status'       => $new_effective === $compare ? 'returned-to-coded-default' : 'wordpress-override',
            );
        }
    }

    if ( $clean ) {
        update_post_meta( $post_id, OR_CONTENT_SLOTS_META, $clean );
    } else {
        delete_post_meta( $post_id, OR_CONTENT_SLOTS_META );
    }

    or_content_slots_append_change_log( $post_id, $changes );
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
