/**
 * External dependencies
 */
import { connect } from 'react-redux';
import { find, flowRight } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component } from '@wordpress/element';
import { PanelRow, Dropdown, withInstanceId, withAPIData } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import './style.scss';
import {
	getEditedPostAttribute,
	getEditedPostVisibility,
} from '../../selectors';
import { editPost, savePost } from '../../actions';

export class PostVisibility extends Component {
	constructor( props ) {
		super( ...arguments );

		this.setPublic = this.setPublic.bind( this );
		this.setPrivate = this.setPrivate.bind( this );
		this.setPasswordProtected = this.setPasswordProtected.bind( this );

		this.state = {
			hasPassword: !! props.password,
		};
	}

	setPublic() {
		const { visibility, onUpdateVisibility, status } = this.props;

		onUpdateVisibility( visibility === 'private' ? 'draft' : status );
		this.setState( { hasPassword: false } );
	}

	setPrivate() {
		if ( ! window.confirm( __( 'Would you like to privately publish this post now?' ) ) ) { // eslint-disable-line no-alert
			return;
		}

		const { onUpdateVisibility, onSave } = this.props;

		onUpdateVisibility( 'private' );
		this.setState( { hasPassword: false } );
		onSave();
	}

	setPasswordProtected() {
		const { visibility, onUpdateVisibility, status, password } = this.props;

		onUpdateVisibility( visibility === 'private' ? 'draft' : status, password || '' );
		this.setState( { hasPassword: true } );
	}

	render() {
		const { status, visibility, password, onUpdateVisibility, instanceId, user } = this.props;
		const canEdit = user.data && user.data.capabilities.publish_posts;

		const updatePassword = ( event ) => onUpdateVisibility( status, event.target.value );

		const visibilityOptions = [
			{
				value: 'public',
				label: __( 'Public' ),
				info: __( 'Visible to everyone.' ),
				onSelect: this.setPublic,
				checked: visibility === 'public' && ! this.state.hasPassword,
			},
			{
				value: 'private',
				label: __( 'Private' ),
				info: __( 'Only visible to site admins and editors.' ),
				onSelect: this.setPrivate,
				checked: visibility === 'private',
			},
			{
				value: 'password',
				label: __( 'Password Protected' ),
				info: __( 'Protected with a password you choose. Only those with the password can view this post.' ),
				onSelect: this.setPasswordProtected,
				checked: this.state.hasPassword,
			},
		];
		const getVisibilityLabel = () => find( visibilityOptions, { value: visibility } ).label;

		// Disable Reason: The input is inside the label, we shouldn't need the htmlFor
		/* eslint-disable jsx-a11y/label-has-for */
		return (
			<PanelRow className="editor-post-visibility">
				<span>{ __( 'Visibility' ) }</span>
				{ ! canEdit && <span>{ getVisibilityLabel( visibility ) }</span> }
				{ canEdit && (
					<Dropdown
						position="bottom left"
						contentClassName="editor-post-visibility__dialog"
						renderToggle={ ( { isOpen, onToggle } ) => (
							<button
								type="button"
								aria-expanded={ isOpen }
								className="editor-post-visibility__toggle button-link"
								onClick={ onToggle }
							>
								{ getVisibilityLabel( visibility ) }
							</button>
						) }
						renderContent={ () => ( [
							<fieldset key="visibility-selector">
								<legend className="editor-post-visibility__dialog-legend">
									{ __( 'Post Visibility' ) }
								</legend>
								{ visibilityOptions.map( ( { value, label, info, onSelect, checked } ) => (
									<div key={ value } className="editor-post-visibility__choice">
										<input
											type="radio"
											name={ `editor-post-visibility__setting-${ instanceId }` }
											value={ value }
											onChange={ onSelect }
											checked={ checked }
											id={ `editor-post-${ value }-${ instanceId }` }
											aria-describedby={ `editor-post-${ value }-${ instanceId }-description` }
											className="editor-post-visibility__dialog-radio"
										/>
										<label
											htmlFor={ `editor-post-${ value }-${ instanceId }` }
											className="editor-post-visibility__dialog-label"
										>
											{ label }
										</label>
										{ <p id={ `editor-post-${ value }-${ instanceId }-description` } className="editor-post-visibility__dialog-info">{ info }</p> }
									</div>
								) ) }
							</fieldset>,
							this.state.hasPassword && (
								<div className="editor-post-visibility__dialog-password" key="password-selector">
									<label
										htmlFor={ `editor-post-visibility__dialog-password-input-${ instanceId }` }
										className="screen-reader-text"
									>
										{ __( 'Create password' ) }
									</label>
									<input
										className="editor-post-visibility__dialog-password-input"
										id={ `editor-post-visibility__dialog-password-input-${ instanceId }` }
										type="text"
										onChange={ updatePassword }
										value={ password }
										placeholder={ __( 'Use a secure password' ) }
									/>
								</div>
							),
						] ) }
					/>
				) }
			</PanelRow>
		);
		/* eslint-enable jsx-a11y/label-has-for */
	}
}

const applyConnect = connect(
	( state ) => ( {
		status: getEditedPostAttribute( state, 'status' ),
		visibility: getEditedPostVisibility( state ),
		password: getEditedPostAttribute( state, 'password' ),
	} ),
	{
		onSave: savePost,
		onUpdateVisibility( status, password = null ) {
			return editPost( { status, password } );
		},
	}
);

const applyWithAPIData = withAPIData( () => {
	return {
		user: `/${ wpApiSettings.versionString }/users/me?context=edit`,
	};
} );

export default flowRight(
	applyConnect,
	applyWithAPIData,
	withInstanceId
)( PostVisibility );
