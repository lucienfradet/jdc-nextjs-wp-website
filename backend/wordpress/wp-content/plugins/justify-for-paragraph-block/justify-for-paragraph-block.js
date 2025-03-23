/**
 * Justify for Paragraph Block
 * 
 * Adds justify alignment functionality to paragraph blocks in Gutenberg
 * 
 * @package JustifyForParagraphBlock
 * @since 1.0.0
 */
(function (wp) {
    // Verify WordPress dependencies are loaded
    if (typeof wp === 'undefined' || typeof wp.element === 'undefined' || typeof wp.blockEditor === 'undefined') {
        console.error('WordPress dependencies not loaded correctly!');
        return;
    }

    const { addFilter } = wp.hooks;
    const { createHigherOrderComponent } = wp.compose;
    const { Fragment } = wp.element;
    const { BlockControls } = wp.blockEditor;
    const { ToolbarButton } = wp.components;
    const { __ } = wp.i18n; // Translation function

    /**
     * Add justify alignment support to paragraph block settings
     * 
     * @param {Object} settings - Block settings
     * @param {string} name - Block name
     * @return {Object} Modified block settings
     */
    function jfpb_addJustifyToSettings(settings, name) {
        // Only modify paragraph block
        if (name !== 'core/paragraph') {
            return settings;
        }

        // Add justify to supported alignments
        if (settings.supports && settings.supports.align) {
            settings.supports.align.push('justify');
        }

        return settings;
    }

    // Register filter to add justify alignment
    addFilter(
        'blocks.registerBlockType',
        'jfpb-justify-for-paragraph-block/add-justify-alignment',
        jfpb_addJustifyToSettings
    );

    /**
     * Add CSS class for justified alignment
     * 
     * @param {Object} extraProps - Additional block properties
     * @param {Object} blockType - Block type
     * @param {Object} attributes - Block attributes
     * @return {Object} Modified extra properties
     */
    function jfpb_addJustifyClass(extraProps, blockType, attributes) {
        // Only modify paragraph block
        if (blockType.name !== 'core/paragraph') {
            return extraProps;
        }

        // Add justify class when alignment is set to justify
        if (attributes.align === 'justify') {
            extraProps.className = extraProps.className 
                ? extraProps.className + ' has-text-align-justify' 
                : 'has-text-align-justify';
        }

        return extraProps;
    }

    // Register filter to add justify class
    addFilter(
        'blocks.getSaveContent.extraProps',
        'jfpb-justify-for-paragraph-block/add-justify-class',
        jfpb_addJustifyClass
    );

    /**
     * Create higher-order component to add justify button
     * 
     * @return {Function} Modified block edit component
     */
    const jfpb_withJustifyButton = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            // Only add button to paragraph block
            if (props.name !== 'core/paragraph') {
                return wp.element.createElement(BlockEdit, props);
            }

            return wp.element.createElement(
                Fragment,
                null,
                wp.element.createElement(BlockEdit, props),
                wp.element.createElement(
                    BlockControls,
                    null,
                    wp.element.createElement(
                        ToolbarButton,
                        {
                            icon: "editor-justify",
                            title: __("Justify"),
                            isActive: props.attributes.align === 'justify',
                            onClick: function () {
                                props.setAttributes({
                                    align: props.attributes.align === 'justify' ? undefined : 'justify',
                                });
                            }
                        }
                    )
                )
            );
        };
    }, 'jfpb_withJustifyButton');

    // Register filter to add justify button
    addFilter(
        'editor.BlockEdit',
        'jfpb-justify-for-paragraph-block/with-justify-button',
        jfpb_withJustifyButton
    );
})(window.wp);