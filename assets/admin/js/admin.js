/**
 * RentAFleet — Admin JavaScript
 *
 * Handles:
 *  • WordPress Media Library integration (featured image, gallery, category image)
 *  • Location checkbox ↔ units field toggle
 *  • Select-all checkboxes for list tables
 *  • Generic admin utilities
 *
 * @package RentAFleet
 * @since   1.0.0
 */

( function( $ ) {
    'use strict';

    /* ================================================================
       MEDIA LIBRARY — Featured Image
       ============================================================= */

    var featuredFrame;

    $( document ).on( 'click', '#raf-set-featured-image', function( e ) {
        e.preventDefault();

        if ( featuredFrame ) {
            featuredFrame.open();
            return;
        }

        featuredFrame = wp.media( {
            title: 'Select Featured Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        } );

        featuredFrame.on( 'select', function() {
            var attachment = featuredFrame.state().get( 'selection' ).first().toJSON();
            var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

            $( '#featured_image_id' ).val( attachment.id );
            $( '#raf-featured-image-preview' ).html( '<img src="' + url + '" alt="">' );
            $( '#raf-remove-featured-image' ).show();
            $( '#raf-set-featured-image' ).text( 'Change Image' );
        } );

        featuredFrame.open();
    } );

    $( document ).on( 'click', '#raf-remove-featured-image', function( e ) {
        e.preventDefault();
        $( '#featured_image_id' ).val( 0 );
        $( '#raf-featured-image-preview' ).empty();
        $( '#raf-remove-featured-image' ).hide();
        $( '#raf-set-featured-image' ).text( 'Set Featured Image' );
    } );

    /* ================================================================
       MEDIA LIBRARY — Gallery
       ============================================================= */

    var galleryFrame;

    $( document ).on( 'click', '#raf-add-gallery-images', function( e ) {
        e.preventDefault();

        if ( galleryFrame ) {
            galleryFrame.open();
            return;
        }

        galleryFrame = wp.media( {
            title: 'Add Gallery Images',
            button: { text: 'Add to gallery' },
            multiple: true,
            library: { type: 'image' }
        } );

        galleryFrame.on( 'select', function() {
            var attachments = galleryFrame.state().get( 'selection' ).toJSON();
            var $preview = $( '#raf-gallery-preview' );
            var $input   = $( '#raf-gallery-ids' );
            var currentIds = $input.val() ? $input.val().split( ',' ).map( Number ) : [];

            attachments.forEach( function( att ) {
                if ( currentIds.indexOf( att.id ) === -1 ) {
                    currentIds.push( att.id );
                    var thumbUrl = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                    $preview.append(
                        '<div class="raf-gallery-thumb" data-id="' + att.id + '">' +
                            '<img src="' + thumbUrl + '" alt="">' +
                            '<button type="button" class="raf-gallery-remove" data-id="' + att.id + '">&times;</button>' +
                        '</div>'
                    );
                }
            } );

            $input.val( currentIds.join( ',' ) );
        } );

        galleryFrame.open();
    } );

    // Remove a gallery image
    $( document ).on( 'click', '.raf-gallery-remove', function( e ) {
        e.preventDefault();
        var removeId = $( this ).data( 'id' );
        var $input   = $( '#raf-gallery-ids' );
        var ids      = $input.val() ? $input.val().split( ',' ).map( Number ) : [];

        ids = ids.filter( function( id ) { return id !== removeId; } );
        $input.val( ids.join( ',' ) );

        $( this ).closest( '.raf-gallery-thumb' ).remove();
    } );

    /* ================================================================
       MEDIA LIBRARY — Category Image
       ============================================================= */

    var catImageFrame;

    $( document ).on( 'click', '#raf-set-cat-image', function( e ) {
        e.preventDefault();

        if ( catImageFrame ) {
            catImageFrame.open();
            return;
        }

        catImageFrame = wp.media( {
            title: 'Select Category Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        } );

        catImageFrame.on( 'select', function() {
            var attachment = catImageFrame.state().get( 'selection' ).first().toJSON();
            var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

            $( '#cat_image_id' ).val( attachment.id );
            $( '#raf-cat-image-preview' ).html( '<img src="' + url + '" alt="">' );
            $( '#raf-remove-cat-image' ).show();
        } );

        catImageFrame.open();
    } );

    $( document ).on( 'click', '#raf-remove-cat-image', function( e ) {
        e.preventDefault();
        $( '#cat_image_id' ).val( 0 );
        $( '#raf-cat-image-preview' ).empty();
        $( '#raf-remove-cat-image' ).hide();
    } );

    /* ================================================================
       LOCATION CHECKBOXES — Toggle units field
       ============================================================= */

    $( document ).on( 'change', '.raf-loc-checkbox', function() {
        var locId = $( this ).data( 'loc' );
        var $units = $( '.raf-loc-units[data-loc="' + locId + '"]' );

        if ( $( this ).is( ':checked' ) ) {
            $units.prop( 'disabled', false );
        } else {
            $units.prop( 'disabled', true );
        }
    } );

    /* ================================================================
       SELECT ALL CHECKBOXES
       ============================================================= */

    $( document ).on( 'change', '#cb-select-all-1, #cb-select-all-2', function() {
        var checked = $( this ).prop( 'checked' );
        $( this ).closest( 'form' ).find( 'tbody input[type="checkbox"]' ).prop( 'checked', checked );
        // Sync the other select-all checkbox
        $( '#cb-select-all-1, #cb-select-all-2' ).prop( 'checked', checked );
    } );

    /* ================================================================
       SORTABLE COLUMN HEADERS — visual feedback
       ============================================================= */

    $( '.manage-column.sortable a' ).on( 'mouseenter', function() {
        $( this ).css( 'color', '#0073aa' );
    } ).on( 'mouseleave', function() {
        $( this ).css( 'color', '' );
    } );

    /* ================================================================
       AUTO-GENERATE SLUG from Name (if slug is empty)
       ============================================================= */

    $( '#vehicle_name' ).on( 'blur', function() {
        var $slug = $( '#vehicle_slug' );
        if ( ! $slug.val() ) {
            var slug = $( this ).val()
                .toLowerCase()
                .replace( /[^a-z0-9\s-]/g, '' )
                .replace( /\s+/g, '-' )
                .replace( /-+/g, '-' )
                .replace( /^-|-$/g, '' );
            $slug.val( slug );
        }
    } );

    /* ================================================================
       CONFIRM DELETE (fallback for non-onclick)
       ============================================================= */

    $( document ).on( 'click', '.submitdelete', function() {
        if ( $( this ).closest( '.raf-publish-actions' ).length ) {
            // Already has onclick confirm on the link itself
            return true;
        }
    } );

    /* ================================================================
       DATEPICKER INIT (for future pages that need it)
       ============================================================= */

    $( function() {
        if ( $.fn.datepicker ) {
            $( '.raf-datepicker' ).datepicker( {
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            } );
        }
    } );

} )( jQuery );
