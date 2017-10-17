/**
 * External dependencies
 */
import { connect } from 'react-redux';
import { noop } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { IconButton } from '@wordpress/components';
import { getBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { getBlockMode, getBlock } from '../selectors';
import { toggleBlockMode } from '../actions';

export function BlockModeToggle( { blockType, mode, onToggleMode } ) {
	if ( blockType.supportHTML === false ) {
		return null;
	}

	return (
		<IconButton
			className="editor-block-settings-menu__control"
			onClick={ onToggleMode }
			icon="html"
		>
			{ mode === 'visual'
				? __( 'Edit as HTML' )
				: __( 'Edit visually' )
			}
		</IconButton>
	);
}

export default connect(
	( state, { uid } ) => ( {
		mode: getBlockMode( state, uid ),
		blockType: getBlockType( getBlock( state, uid ).name ),
	} ),
	( dispatch, { onToggle = noop, uid } ) => ( {
		onToggleMode() {
			dispatch( toggleBlockMode( uid ) );
			onToggle();
		},
	} )
)( BlockModeToggle );
