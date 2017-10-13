/**
 * External dependencies
 */
import Clipboard from 'clipboard';
import classnames from 'classnames';
import { noop } from 'lodash';

/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button } from '../';

function ClipboardButton( { className, children, onCopy, text } ) {
	const classes = classnames( 'components-clipboard-button', className );

	return [
		<Button key="button" className={ classes }>
			{ children }
		</Button>,
		<ClipboardButton.Container
			key="container"
			onCopy={ onCopy }
			text={ text }
		/>,
	];
}

ClipboardButton.Container = class extends Component {
	componentDidMount() {
		const { text, onCopy = noop } = this.props;
		this.clipboard = new Clipboard( this.container.previousElementSibling, {
			text: () => text,
			container: this.container,
		} );
		this.clipboard.on( 'success', onCopy );
	}

	componentWillUnmount() {
		this.clipboard.destroy();
		delete this.clipboard;
	}

	componentShouldUpate() {
		return false;
	}

	render() {
		return <span ref={ ref => this.container = ref } />;
	}
};

export default ClipboardButton;
