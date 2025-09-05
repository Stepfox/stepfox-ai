(function(wp){
	const { registerBlockType } = wp.blocks;
	const { TextareaControl, PanelBody, ToggleControl, RangeControl, Button } = wp.components;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
	const { createElement: el, Fragment, useState, useEffect, useRef } = wp.element;

	function isHtmlLike(code){ return /(<!doctype|<html|<head|<body|<meta|<link|<style|<script)/i.test(String(code||'')); }

	registerBlockType('stepfox/three-js', {
		title: 'Three.js',
		icon: 'format-aside',
		category: 'widgets',
		supports: { html: false },
		attributes: {
			code: { type: 'string', default: '' },
			height: { type: 'number', default: 480 },
			background: { type: 'string', default: 'transparent' },
			autoLoadThree: { type: 'boolean', default: true },
		},
		edit: function(props){
			const { attributes, setAttributes } = props;
			const { code, height, autoLoadThree } = attributes;
			const blockProps = useBlockProps({ className: 'sfl-threejs-editor' });
			const reloadKeyRef = useRef(0);
			const containerRef = useRef(null);

			useEffect(function(){ reloadKeyRef.current++; }, [code, autoLoadThree, height]);

			function runPreview(){
				try{ if (containerRef.current && window.StepfoxThreeInlineRunner){ window.StepfoxThreeInlineRunner.mount(containerRef.current); } }catch(e){}
			}

			return el(Fragment, null,
				el(InspectorControls, null,
					el(PanelBody, { title: 'Canvas Settings', initialOpen: true },
						el(RangeControl, {
							label: 'Height (px)', min: 200, max: 1600, value: height,
							onChange: function(v){ setAttributes({ height: v }); }
						}),
						el(ToggleControl, {
							label: 'Auto-load Three.js',
							checked: !!autoLoadThree,
							onChange: function(v){ setAttributes({ autoLoadThree: v }); }
						})
					)
				),
				el('div', Object.assign({}, blockProps, { style: { border: '1px solid #ddd', borderRadius: 6, overflow: 'hidden', background:'transparent' } }),
					el('div', { style: { background: '#f6f7f7', padding: '10px 12px', borderBottom: '1px solid #e0e0e0', fontWeight: 600 } }, 'Three.js Code'),
					el('div', { style: { padding: 12 } },
						el(TextareaControl, {
							label: null,
							value: code,
							onChange: function(v){ setAttributes({ code: v }); },
							rows: 18,
							placeholder: "Paste JavaScript or a full HTML page (<!doctype>, <html>...)."
						})
					),
					el('div', { style: { background: '#f6f7f7', padding: '10px 12px', borderTop: '1px solid #e0e0e0', fontWeight: 600, display:'flex', alignItems:'center', justifyContent:'space-between' } },
						el('span', null, 'Live Preview'),
						el(Button, { isSecondary: true, onClick: runPreview }, 'Run')
					),
					el('div', { style: { height: height + 'px', background: 'transparent', position: 'relative' } },
						(function(){
							var key = String(Date.now()) + '-' + String(reloadKeyRef.current);
							var payload = {
								code: code,
								autoLoadThree: !!autoLoadThree,
								local1: (window.stepfoxThreeJs && window.stepfoxThreeJs.localPlugin) || '',
								local2: (window.stepfoxThreeJs && window.stepfoxThreeJs.localTheme) || '',
								isHtml: isHtmlLike(code)
							};
							return el('div', {
								key: key,
								className: 'sfa-three-inline',
								style: { position:'absolute', inset:0 },
								ref: containerRef
							},
								el('div', { className: 'sfa-three-canvas', style: { position:'absolute', inset:0 } }),
								el('div', { 'data-payload': JSON.stringify(payload), style: { display:'none' } })
							);
						})()
					)
				)
			);
		},
		save: function(){ return null; },
	});
})(window.wp);


