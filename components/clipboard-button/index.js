/**
 * External dependencies
 */
import Clipboard from 'clipboard';
import classnames from 'classnames';

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
	constructor() {
		super( ...arguments );

		this.onCopy = this.onCopy.bind( this );
		this.getText = this.getText.bind( this );
	}

	componentDidMount() {
		const { container, getText, onCopy } = this;

		this.clipboard = new Clipboard(
			container.previousElementSibling,
			{ text: getText, container }
		);

		this.clipboard.on( 'success', onCopy );
	}

	onCopy() {
		const { onCopy } = this.props;
		if ( onCopy ) {
			onCopy();
		}
	}

	getText() {
		return this.props.text;
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
