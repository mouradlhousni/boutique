/**
 * Editor side of the "Artwork Series Showcase" block: settings in the sidebar,
 * live preview rendered by the server (render.php), so the editor and the site match.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	CheckboxControl,
	PanelBody,
	RangeControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.css';

function Edit( { attributes, setAttributes } ) {
	const { heading, series, worksPerSeries, showDescription } = attributes;
	const blockProps = useBlockProps();

	const terms = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords(
				'taxonomy',
				'artwork_series',
				{
					per_page: 50,
					hide_empty: true,
				}
			),
		[]
	);

	const toggleSeries = ( id, checked ) =>
		setAttributes( {
			series: checked
				? [ ...series, id ]
				: series.filter( ( item ) => item !== id ),
		} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Series', 'atelier-mira' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'atelier-mira' ) }
						value={ heading }
						onChange={ ( value ) =>
							setAttributes( { heading: value } )
						}
					/>
					<p>
						{ __(
							'Choose the series to show. None selected shows them all, in studio order.',
							'atelier-mira'
						) }
					</p>
					{ ! terms && <Spinner /> }
					{ terms &&
						terms.map( ( term ) => (
							<CheckboxControl
								__nextHasNoMarginBottom
								key={ term.id }
								label={ term.name }
								checked={ series.includes( term.id ) }
								onChange={ ( checked ) =>
									toggleSeries( term.id, checked )
								}
							/>
						) ) }
				</PanelBody>
				<PanelBody title={ __( 'Display', 'atelier-mira' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Works per series', 'atelier-mira' ) }
						value={ worksPerSeries }
						min={ 2 }
						max={ 6 }
						onChange={ ( value ) =>
							setAttributes( { worksPerSeries: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show descriptions', 'atelier-mira' ) }
						checked={ showDescription }
						onChange={ ( value ) =>
							setAttributes( { showDescription: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
