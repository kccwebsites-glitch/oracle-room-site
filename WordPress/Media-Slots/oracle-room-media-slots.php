/**
 * Oracle Room Media Slots
 *
 * Lightweight Media Library assignments for bespoke Elementor HTML objects.
 * Canonical component structure lives in GitHub; WordPress owns chosen media.
 *
 * Code Snippets FREE: add as one PHP snippet and run everywhere.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

define( 'OR_MEDIA_SLOTS_META', '_or_media_slots' );
define( 'OR_MEDIA_MANIFEST_META', '_or_media_manifest' );

function or_media_slots_supported_post_types() {
    return array( 'page', 'post' );
}

function or_media_slots_supported( $post_id ) {
    return in_array( get_post_type( $post_id ), or_media_slots_supported_post_types(), true );
}

function or_media_slots_decode_elementor( $post_id ) {
    $raw = get_post_meta( $post_id, '_elementor_data', true );

    if ( empty( $raw ) ) {
        return array();
    }

    $decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

    return is_array( $decoded ) ? $decoded : array();
}

function or_media_slots_discover( $post_id ) {
    $decoded = or_media_slots_decode_elementor( $post_id );

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
                    if ( ! is_string( $value ) || false === strpos( $value, 'data-or-media-slot' ) ) {
                        continue;
                    }

                    $pattern = '/<[^>]*\\bdata-or-media-slot\\s*=\\s*["\\\']([^"\\\']+)["\\\'][^>]*>/i';

                    if ( ! preg_match_all( $pattern, $value, $matches ) ) {
                        continue;
                    }

                    foreach ( $matches[0] as $index => $tag ) {
                        $slot = sanitize_key( $matches[1][ $index ] );

                        if ( ! $slot ) {
                            continue;
                        }

                        $type  = 'image';
                        $label = ucwords( str_replace( array( '-', '_' ), ' ', $slot ) );

                        if ( preg_match( '/\\bdata-or-media-type\\s*=\\s*["\\\']([^"\\\']+)["\\\']/i', $tag, $m ) ) {
                            $candidate = sanitize_key( $m[1] );
                            if ( in_array( $candidate, array( 'image', 'gallery', 'youtube' ), true ) ) {
                                $type = $candidate;
                            }
                        }

                        if ( preg_match( '/\\bdata-or-media-label\\s*=\\s*["\\\']([^"\\\']+)["\\\']/i', $tag, $m ) ) {
                            $label = sanitize_text_field( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
                        }

                        $manifest[ $slot ] = array(
                            'type'  => $type,
                            'label' => $label,
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

function or_media_slots_refresh( $post_id ) {
    if ( ! or_media_slots_supported( $post_id ) ) {
        return;
    }

    $manifest = or_media_slots_discover( $post_id );

    if ( $manifest ) {
        update_post_meta( $post_id, OR_MEDIA_MANIFEST_META, $manifest );
        return;
    }

    delete_post_meta( $post_id, OR_MEDIA_MANIFEST_META );
    delete_post_meta( $post_id, OR_MEDIA_SLOTS_META );
}

function or_media_slots_manifest( $post_id ) {
    $manifest = get_post_meta( $post_id, OR_MEDIA_MANIFEST_META, true );

    if ( ! is_array( $manifest ) || ! $manifest ) {
        $manifest = or_media_slots_discover( $post_id );
        if ( $manifest ) {
            update_post_meta( $post_id, OR_MEDIA_MANIFEST_META, $manifest );
        }
    }

    return is_array( $manifest ) ? $manifest : array();
}

add_action( 'save_post', function( $post_id ) {
    if ( ! or_media_slots_supported( $post_id ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( current_user_can( 'edit_post', $post_id ) ) {
        or_media_slots_refresh( $post_id );
    }
}, 30 );

add_action( 'elementor/editor/after_save', function( $post_id ) {
    or_media_slots_refresh( absint( $post_id ) );
}, 30 );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    wp_enqueue_media();
} );

add_action( 'add_meta_boxes', function( $post_type, $post ) {
    if ( ! $post || ! in_array( $post_type, or_media_slots_supported_post_types(), true ) ) {
        return;
    }

    if ( ! or_media_slots_manifest( $post->ID ) ) {
        return;
    }

    add_meta_box(
        'or-media-slots',
        'Oracle Room Media',
        'or_media_slots_metabox',
        $post_type,
        'normal',
        'high'
    );
}, 10, 2 );

function or_media_slots_metabox( $post ) {
    $manifest = or_media_slots_manifest( $post->ID );
    $values   = get_post_meta( $post->ID, OR_MEDIA_SLOTS_META, true );
    $values   = is_array( $values ) ? $values : array();

    wp_nonce_field( 'or_media_slots_save', 'or_media_slots_nonce' );

    echo '<p style="margin-top:0;color:#646970">Assign WordPress Media Library items to the media positions declared by the page objects.</p>';

    foreach ( $manifest as $slot => $definition ) {
        $type  = isset( $definition['type'] ) ? $definition['type'] : 'image';
        $label = isset( $definition['label'] ) ? $definition['label'] : $slot;
        $value = isset( $values[ $slot ] ) && is_array( $values[ $slot ] ) ? $values[ $slot ] : array();

        echo '<div class="or-media-slot-admin" data-or-admin-slot="' . esc_attr( $slot ) . '" data-or-admin-type="' . esc_attr( $type ) . '" style="border:1px solid #dcdcde;padding:14px;margin:0 0 16px;background:#fff">';
        echo '<strong style="display:block;margin-bottom:4px">' . esc_html( $label ) . '</strong>';
        echo '<div style="font-size:12px;color:#646970;margin-bottom:10px">' . esc_html( $slot ) . ' · ' . esc_html( $type ) . '</div>';

        if ( 'youtube' === $type ) {
            $url = isset( $value['url'] ) ? $value['url'] : '';
            echo '<input type="url" name="or_media_slots[' . esc_attr( $slot ) . '][url]" value="' . esc_attr( $url ) . '" placeholder="https://www.youtube.com/watch?v=…" style="width:100%">';
        } elseif ( 'gallery' === $type ) {
            $ids = isset( $value['attachment_ids'] ) && is_array( $value['attachment_ids'] )
                ? implode( ',', array_map( 'absint', $value['attachment_ids'] ) )
                : '';
            echo '<input class="or-media-ids" type="hidden" name="or_media_slots[' . esc_attr( $slot ) . '][attachment_ids]" value="' . esc_attr( $ids ) . '">';
            echo '<button type="button" class="button or-media-choose">Choose gallery</button> ';
            echo '<button type="button" class="button-link-delete or-media-clear">Clear</button>';
            echo '<div class="or-media-summary" style="margin-top:8px;color:#646970">' . esc_html( $ids ? count( array_filter( explode( ',', $ids ) ) ) . ' image(s) selected' : 'No gallery selected' ) . '</div>';
        } else {
            $attachment_id = isset( $value['attachment_id'] ) ? absint( $value['attachment_id'] ) : 0;
            echo '<input class="or-media-id" type="hidden" name="or_media_slots[' . esc_attr( $slot ) . '][attachment_id]" value="' . esc_attr( $attachment_id ) . '">';
            echo '<button type="button" class="button or-media-choose">Choose image</button> ';
            echo '<button type="button" class="button-link-delete or-media-clear">Clear</button>';
            echo '<div class="or-media-summary" style="margin-top:8px;color:#646970">';
            if ( $attachment_id ) {
                echo wp_kses_post( wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'style' => 'max-width:120px;height:auto;display:block;margin-top:8px' ) ) );
            } else {
                echo 'No image selected';
            }
            echo '</div>';
        }

        if ( in_array( $type, array( 'image', 'gallery' ), true ) ) {
            $fit      = isset( $value['fit'] ) ? sanitize_key( $value['fit'] ) : 'default';
            $position = isset( $value['position'] ) ? sanitize_key( $value['position'] ) : 'default';

            echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px">';
            echo '<label>Fit<br><select name="or_media_slots[' . esc_attr( $slot ) . '][fit]">';
            foreach ( array( 'default' => 'Theme/default', 'cover' => 'Cover', 'contain' => 'Contain' ) as $key => $text ) {
                echo '<option value="' . esc_attr( $key ) . '"' . selected( $fit, $key, false ) . '>' . esc_html( $text ) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Focal position<br><select name="or_media_slots[' . esc_attr( $slot ) . '][position]">';
            foreach ( array(
                'default' => 'Theme/default',
                'center'  => 'Centre',
                'top'     => 'Top',
                'bottom'  => 'Bottom',
                'left'    => 'Left',
                'right'   => 'Right',
            ) as $key => $text ) {
                echo '<option value="' . esc_attr( $key ) . '"' . selected( $position, $key, false ) . '>' . esc_html( $text ) . '</option>';
            }
            echo '</select></label>';
            echo '</div>';
        }

        echo '</div>';
    }

    ?>
    <script>
    (() => {
        if (window.__oracleRoomMediaAdminReady) return;
        window.__oracleRoomMediaAdminReady = true;

        document.addEventListener('click', (event) => {
            const choose = event.target.closest('.or-media-choose');
            const clear = event.target.closest('.or-media-clear');

            if (!choose && !clear) return;

            const wrap = event.target.closest('.or-media-slot-admin');
            if (!wrap) return;

            const type = wrap.dataset.orAdminType;

            if (clear) {
                const id = wrap.querySelector('.or-media-id');
                const ids = wrap.querySelector('.or-media-ids');
                if (id) id.value = '';
                if (ids) ids.value = '';
                const summary = wrap.querySelector('.or-media-summary');
                if (summary) summary.textContent = type === 'gallery' ? 'No gallery selected' : 'No image selected';
                return;
            }

            const frame = wp.media({
                title: type === 'gallery' ? 'Choose gallery images' : 'Choose image',
                button: { text: type === 'gallery' ? 'Use gallery' : 'Use image' },
                library: { type: 'image' },
                multiple: type === 'gallery'
            });

            frame.on('select', () => {
                const selection = frame.state().get('selection').toJSON();
                const summary = wrap.querySelector('.or-media-summary');

                if (type === 'gallery') {
                    const ids = selection.map(item => item.id);
                    const input = wrap.querySelector('.or-media-ids');
                    if (input) input.value = ids.join(',');
                    if (summary) summary.textContent = ids.length + ' image(s) selected';
                    return;
                }

                const item = selection[0];
                const input = wrap.querySelector('.or-media-id');
                if (input && item) input.value = item.id;

                if (summary && item) {
                    const url = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
                    summary.innerHTML = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:120px;height:auto;display:block;margin-top:8px">';
                }
            });

            frame.open();
        });
    })();
    </script>
    <?php
}

add_action( 'save_post', function( $post_id ) {
    if ( ! or_media_slots_supported( $post_id ) ) {
        return;
    }

    if ( ! isset( $_POST['or_media_slots_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['or_media_slots_nonce'] ) ), 'or_media_slots_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $manifest = or_media_slots_manifest( $post_id );
    $posted   = isset( $_POST['or_media_slots'] ) && is_array( $_POST['or_media_slots'] )
        ? wp_unslash( $_POST['or_media_slots'] )
        : array();

    $clean = array();

    foreach ( $manifest as $slot => $definition ) {
        $type = isset( $definition['type'] ) ? $definition['type'] : 'image';
        $raw  = isset( $posted[ $slot ] ) && is_array( $posted[ $slot ] ) ? $posted[ $slot ] : array();

        if ( 'youtube' === $type ) {
            $url = isset( $raw['url'] ) ? esc_url_raw( trim( $raw['url'] ) ) : '';
            if ( $url ) {
                $clean[ $slot ] = array( 'type' => 'youtube', 'url' => $url );
            }
            continue;
        }

        $fit = isset( $raw['fit'] ) && in_array( $raw['fit'], array( 'default', 'cover', 'contain' ), true )
            ? $raw['fit']
            : 'default';

        $position = isset( $raw['position'] ) && in_array( $raw['position'], array( 'default', 'center', 'top', 'bottom', 'left', 'right' ), true )
            ? $raw['position']
            : 'default';

        if ( 'gallery' === $type ) {
            $ids = isset( $raw['attachment_ids'] )
                ? array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $raw['attachment_ids'] ) ) ) ) )
                : array();

            $ids = array_values( array_filter( $ids, function( $attachment_id ) {
                return 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id );
            } ) );

            if ( $ids ) {
                $clean[ $slot ] = array(
                    'type'           => 'gallery',
                    'attachment_ids' => $ids,
                    'fit'            => $fit,
                    'position'       => $position,
                );
            }
            continue;
        }

        $attachment_id = isset( $raw['attachment_id'] ) ? absint( $raw['attachment_id'] ) : 0;

        if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
            $clean[ $slot ] = array(
                'type'          => 'image',
                'attachment_id' => $attachment_id,
                'fit'           => $fit,
                'position'      => $position,
            );
        }
    }

    if ( $clean ) {
        update_post_meta( $post_id, OR_MEDIA_SLOTS_META, $clean );
    } else {
        delete_post_meta( $post_id, OR_MEDIA_SLOTS_META );
    }
}, 20 );

function or_media_slots_youtube_id( $url ) {
    $parts = wp_parse_url( $url );

    if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
        return '';
    }

    $host = strtolower( preg_replace( '/^www\\./', '', $parts['host'] ) );
    $id   = '';

    if ( 'youtu.be' === $host ) {
        $id = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';
    } elseif ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com' ), true ) ) {
        $path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

        if ( isset( $parts['query'] ) ) {
            parse_str( $parts['query'], $query );
            if ( ! empty( $query['v'] ) ) {
                $id = $query['v'];
            }
        }

        if ( ! $id && preg_match( '#^(?:embed|shorts|live)/([^/?]+)#', $path, $m ) ) {
            $id = $m[1];
        }
    }

    return preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) ? $id : '';
}

function or_media_slots_payload( $post_id ) {
    $manifest = or_media_slots_manifest( $post_id );
    $values   = get_post_meta( $post_id, OR_MEDIA_SLOTS_META, true );
    $values   = is_array( $values ) ? $values : array();
    $payload  = array();

    foreach ( $manifest as $slot => $definition ) {
        if ( empty( $values[ $slot ] ) || ! is_array( $values[ $slot ] ) ) {
            continue;
        }

        $value = $values[ $slot ];
        $type  = isset( $definition['type'] ) ? $definition['type'] : 'image';

        if ( 'youtube' === $type && ! empty( $value['url'] ) ) {
            $id = or_media_slots_youtube_id( $value['url'] );

            if ( $id ) {
                $src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id ) . '?rel=0';
                $payload[ $slot ] = array(
                    'type' => 'youtube',
                    'html' => '<iframe src="' . esc_url( $src ) . '" title="' . esc_attr( $definition['label'] ) . '" loading="lazy" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
                );
            }
            continue;
        }

        if ( 'gallery' === $type && ! empty( $value['attachment_ids'] ) && is_array( $value['attachment_ids'] ) ) {
            $html = '';

            foreach ( $value['attachment_ids'] as $attachment_id ) {
                $image = wp_get_attachment_image(
                    absint( $attachment_id ),
                    'large',
                    false,
                    array(
                        'class'    => 'or-gallery-image',
                        'loading'  => 'lazy',
                        'decoding' => 'async',
                    )
                );

                if ( $image ) {
                    $html .= '<figure class="or-gallery-item">' . $image . '</figure>';
                }
            }

            if ( $html ) {
                $payload[ $slot ] = array(
                    'type'     => 'gallery',
                    'html'     => $html,
                    'fit'      => isset( $value['fit'] ) ? $value['fit'] : 'default',
                    'position' => isset( $value['position'] ) ? $value['position'] : 'default',
                );
            }
            continue;
        }

        if ( 'image' === $type && ! empty( $value['attachment_id'] ) ) {
            $html = wp_get_attachment_image(
                absint( $value['attachment_id'] ),
                'full',
                false,
                array(
                    'class'    => 'or-slot-image',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                )
            );

            if ( $html ) {
                $payload[ $slot ] = array(
                    'type'     => 'image',
                    'html'     => $html,
                    'fit'      => isset( $value['fit'] ) ? $value['fit'] : 'default',
                    'position' => isset( $value['position'] ) ? $value['position'] : 'default',
                );
            }
        }
    }

    return $payload;
}

add_action( 'wp_footer', function() {
    if ( ! is_singular( or_media_slots_supported_post_types() ) ) {
        return;
    }

    $payload = or_media_slots_payload( get_queried_object_id() );

    if ( ! $payload ) {
        return;
    }
    ?>
    <script id="oracle-room-media-slots-runtime">
    (() => {
        const slots = <?php echo wp_json_encode( $payload ); ?>;

        Object.entries(slots).forEach(([slot, data]) => {
            document.querySelectorAll('[data-or-media-slot="' + CSS.escape(slot) + '"]').forEach((target) => {
                target.innerHTML = data.html;

                if (data.fit && data.fit !== 'default') {
                    target.querySelectorAll('img').forEach((img) => {
                        img.style.setProperty('object-fit', data.fit, 'important');
                    });
                }

                if (data.position && data.position !== 'default') {
                    target.querySelectorAll('img').forEach((img) => {
                        img.style.setProperty('object-position', data.position, 'important');
                    });
                }

                target.classList.add('or-media-slot--loaded');
                target.dispatchEvent(new CustomEvent('or:media-loaded', {
                    bubbles: true,
                    detail: { slot, type: data.type }
                }));
            });
        });
    })();
    </script>
    <?php
}, 50 );
