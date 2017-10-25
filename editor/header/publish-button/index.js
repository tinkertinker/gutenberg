/* global wpApiSettings */

/**
 * External dependencies
 */
import { connect } from 'react-redux';
import classnames from 'classnames';
import { flowRight, noop } from 'lodash';

/**
 * WordPress dependencies
 */
import { Button, withAPIData } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import PublishButtonLabel from './label';
import { editPost, savePost } from '../../actions';
import {
	isSavingPost,
	isEditedPostBeingScheduled,
	getEditedPostVisibility,
	isEditedPostSaveable,
	isEditedPostPublishable,
} from '../../selectors';

export function PublishButton( {
	isSaving,
	onStatusChange,
	onSave,
	isBeingScheduled,
	visibility,
	isPublishable,
	isSaveable,
	user,
	onSubmit = noop,
} ) {
	user = {
		data: {
			"id":388905,
			"name":"George Hotelling",
			"url":"https:\/\/crud.blog\/",
			"description":"",
			"link":"https:\/\/testp2a8c.wordpress.com\/author\/georgehotelling\/",
			"slug":"georgehotelling",
			"avatar_urls":{
				"24":"https:\/\/secure.gravatar.com\/avatar\/9c9aa771f98b781c3fea988f6d925c9f?s=24&d=identicon&r=g",
				"48":"https:\/\/secure.gravatar.com\/avatar\/9c9aa771f98b781c3fea988f6d925c9f?s=48&d=identicon&r=g",
				"96":"https:\/\/secure.gravatar.com\/avatar\/9c9aa771f98b781c3fea988f6d925c9f?s=96&d=identicon&r=g"
			},
			"meta":[],
			"_links":{
				"self":[ { "href":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/users\/388905" } ],
				"collection":[{"href":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/users"}]
			},
			capabilities: {
				publish_posts: true
			}
		}
	};
	const isButtonEnabled = user.data && ! isSaving && isPublishable && isSaveable;
	const isContributor = user.data && ! user.data.capabilities.publish_posts;

	let publishStatus;
	if ( isContributor ) {
		publishStatus = 'pending';
	} else if ( isBeingScheduled ) {
		publishStatus = 'future';
	} else if ( visibility === 'private' ) {
		publishStatus = 'private';
	} else {
		publishStatus = 'publish';
	}

	const className = classnames( 'editor-publish-button', {
		'is-saving': isSaving,
	} );

	const onClick = () => {
		onSubmit();
		onStatusChange( publishStatus );
		onSave();
	};

	return (
		<Button
			isPrimary
			isLarge
			onClick={ onClick }
			disabled={ ! isButtonEnabled }
			className={ className }
		>
			<PublishButtonLabel />
		</Button>
	);
}

const applyConnect = connect(
	( state ) => ( {
		isSaving: isSavingPost( state ),
		isBeingScheduled: isEditedPostBeingScheduled( state ),
		visibility: getEditedPostVisibility( state ),
		isSaveable: isEditedPostSaveable( state ),
		isPublishable: isEditedPostPublishable( state ),
	} ),
	{
		onStatusChange: ( status ) => editPost( { status } ),
		onSave: savePost,
	}
);

const applyWithAPIData = withAPIData( () => {
	return {
		user: `/${ wpApiSettings.versionString }users/me?context=edit`,
	};
} );

export default flowRight( [
	applyConnect,
	applyWithAPIData,
] )( PublishButton );
