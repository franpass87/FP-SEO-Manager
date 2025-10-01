(function( $ ) {
'use strict';

const config = window.fpSeoPerformanceBulk || {};
const $form = $( '[data-fp-seo-bulk-form]' );

if ( ! $form.length ) {
return;
}

const chunkSize = parseInt( config.chunkSize, 10 ) || 5;
const ajaxUrl = config.ajaxUrl || '';
const action = config.action || '';
const messages = config.messages || {};
const $status = $form.find( '[data-fp-seo-bulk-status]' );
const $analyzeButton = $form.find( '[data-fp-seo-bulk-analyze]' );
const $selectAll = $form.find( '[data-fp-seo-bulk-select-all]' );
const rowSelector = '[data-fp-seo-bulk-row]';
const interactiveSelector = 'a, button, input, label, select, textarea';

syncAllRows();

$analyzeButton.on( 'click', function() {
const ids = getSelectedIds();

if ( ! ids.length ) {
announce( messages.noneSelected || '' );
return;
}

disableControls( true );
processChunks( ids ).always( function() {
disableControls( false );
} );
} );

$selectAll.on( 'change', function() {
const checked = $( this ).prop( 'checked' );
$form.find( 'input[name="post_ids[]"]' )
.prop( 'checked', checked )
.each( function() {
setRowSelection( $( this ).closest( rowSelector ), checked );
} );
} );

$form.on( 'change', 'input[name="post_ids[]"]', function() {
const $checkbox = $( this );
setRowSelection( $checkbox.closest( rowSelector ), $checkbox.prop( 'checked' ) );
} );

$form.on( 'click', rowSelector, function( event ) {
if ( shouldIgnoreEvent( event ) ) {
return;
}

const $row = $( this );
const $checkbox = $row.find( 'input[name="post_ids[]"]' );

if ( ! $checkbox.length ) {
return;
}

const nextState = ! $checkbox.prop( 'checked' );
$checkbox.prop( 'checked', nextState ).trigger( 'change' );
focusRow( $row );
} );

$form.on( 'keydown', rowSelector, function( event ) {
const key = event.key;
const $row = $( this );

if ( 'ArrowDown' === key || 'Down' === key ) {
event.preventDefault();
focusRow( $row.nextAll( rowSelector ).first() );
return;
}

if ( 'ArrowUp' === key || 'Up' === key ) {
event.preventDefault();
focusRow( $row.prevAll( rowSelector ).first() );
return;
}

if ( ' ' === key || 'Spacebar' === key ) {
event.preventDefault();
const $checkbox = $row.find( 'input[name="post_ids[]"]' );

if ( $checkbox.length ) {
const nextState = ! $checkbox.prop( 'checked' );
$checkbox.prop( 'checked', nextState ).trigger( 'change' );
}

return;
}

if ( 'Enter' === key ) {
const $link = $row.find( 'a' ).first();

if ( $link.length ) {
event.preventDefault();
window.location.assign( $link.attr( 'href' ) );
}
}
} );

function disableControls( disabled ) {
$analyzeButton.prop( 'disabled', disabled );
$form.find( 'input[name="post_ids[]"]' ).prop( 'disabled', disabled );
$selectAll.prop( 'disabled', disabled );
$analyzeButton.attr( 'aria-disabled', disabled ? 'true' : 'false' );
}

function getSelectedIds() {
return $form
.find( 'input[name="post_ids[]"]:checked' )
.map( function() {
return $( this ).val();
} )
.get();
}

function processChunks( ids ) {
const deferred = $.Deferred();
const batches = [];

for ( let i = 0; i < ids.length; i += chunkSize ) {
batches.push( ids.slice( i, i + chunkSize ) );
}

let processed = 0;

function next() {
if ( ! batches.length ) {
announce( formatMessage( messages.complete || '', processed ) );
deferred.resolve();
return;
}

const batch = batches.shift();
announce( formatMessage( messages.processing || '', processed + batch.length, ids.length ) );

$.post( ajaxUrl, {
action,
nonce: config.nonce,
post_ids: batch,
} )
.done( function( response ) {
if ( ! response || ! response.success || ! response.data || ! response.data.results ) {
announce( messages.error || '' );
deferred.reject();
return;
}

updateRows( response.data.results );
processed += response.data.results.length;
next();
} )
.fail( function() {
announce( messages.error || '' );
deferred.reject();
} );
}

next();

return deferred.promise();
}

function updateRows( results ) {
results.forEach( function( row ) {
const postId = row.post_id || row.postId;

if ( ! postId ) {
return;
}

const $row = $form.find( '[data-post-id="' + postId + '"]' );

if ( ! $row.length ) {
return;
}

if ( row.status ) {
$row.attr( 'data-status', row.status );
}

const score = typeof row.score !== 'undefined' ? row.score : '—';
const warnings = typeof row.warnings !== 'undefined' ? row.warnings : '—';
const updated = row.updated_h || row.updatedHuman || row.updated_human || '';

$row.find( '[data-fp-seo-bulk-score]' ).text( score === '—' ? '—' : row.score );
$row.find( '[data-fp-seo-bulk-warnings]' ).text( warnings === '—' ? '—' : warnings );
$row.find( '[data-fp-seo-bulk-updated]' ).text( updated || ( row.updated ? row.updated : '—' ) );
setRowSelection( $row, $row.find( 'input[name="post_ids[]"]' ).prop( 'checked' ) );
} );
}

function formatMessage( template, value1, value2 ) {
if ( 'string' !== typeof template ) {
return '';
}

let message = template;

if ( 'undefined' !== typeof value2 ) {
message = message.replace( '%2$d', value2 );
}

if ( 'undefined' !== typeof value1 ) {
message = message.replace( '%1$d', value1 );
}

return message;
}

function announce( text ) {
if ( ! text ) {
$status.text( '' ).attr( 'hidden', true );
return;
}

$status.text( text ).removeAttr( 'hidden' );
}

function focusRow( $row ) {
if ( $row && $row.length ) {
$row.focus();
}
}

function shouldIgnoreEvent( event ) {
const target = event.target;

if ( ! target ) {
return false;
}

return $( target ).closest( interactiveSelector ).length > 0;
}

function setRowSelection( $row, selected ) {
if ( ! $row || ! $row.length ) {
return;
}

$row.attr( 'aria-selected', selected ? 'true' : 'false' );
}

function syncAllRows() {
$form.find( rowSelector ).each( function() {
const $row = $( this );
const $checkbox = $row.find( 'input[name="post_ids[]"]' );
setRowSelection( $row, $checkbox.length ? $checkbox.prop( 'checked' ) : false );
} );
}
})( jQuery );
