console.log(`Elementor loaded editor phab-nested-accordion `);

// this iss the magic that makes the nested element work in the editor.
// It listens for the event fired by elementor when the nested element
// type is loaded, and then registers our custom nested element type with
// the editor. This allows us to use our custom nested element in the
// editor and have it render correctly.
//
// we need to wait for the "elementor/nested-element-type-loaded"
// event because the nested element types are loaded asynchronously by Elementor,
// and we need to make sure that our custom nested element type is registered
// after the default nested element types are loaded.

elementorCommon.elements.$window.on(
  "elementor/nested-element-type-loaded",
  async () => {
    class View
      extends $e.components.components["nested-elements"].exports.NestedView
    {
      onAddChild(childView) {
        const accordionId = childView._parent.$el
          .find("summary")
          ?.attr("aria-controls");
        childView.$el.attr({
          role: "region",
          "aria-labelledby": accordionId,
        });
      }
    }

    class PhabNestedAccordion
      extends elementor.modules.elements.types.NestedElementBase
    {
      getType() {
        return "phab-nested-accordion";
      }
      getView() {
        return View;
      }
    }

    elementor.elementsManager.registerElementType(new PhabNestedAccordion());
  },
);

jQuery(window).on("elementor:init", function () {
  // Listen for when a user opens our widget editor panel
  // disable all reordering and duplication functionality within the
  // repeater control, to prevent users from accidentally breaking
  // the structure of the A/B test items.
  // We also remove the "Add Item" button to enforce a fixed number of items
  elementor.hooks.addAction(
    "panel/open_editor/widget/phab-nested-accordion",
    function (panel, model, view) {
      console.log(
        "phab: Custom action triggered for opening the widget editor panel!",
      );

      if ("phab-nested-accordion" === model.get("widgetType")) {
        // Wait for the control to render
        setTimeout(function () {
          // Find the repeater control element and disable drag/drop
          var $repeater = panel.$el.find(".phab-nested-accordion-repeater");

          console.log("phab: Found the repeater control:", $repeater);

          // Disable the sortable functionality
          $repeater
            .find(".elementor-repeater-fields-wrapper")
            .sortable("disable");
        }, 10);
      }

      // Hide "Duplicate" and "Move" (Remove) icons within the repeater
      panel.$el
        .find(
          ".elementor-repeater-row-tools .elementor-repeater-tool-duplicate",
        )
        .hide();
      panel.$el
        .find(".elementor-repeater-row-tools .elementor-repeater-tool-remove")
        .hide();

      // Disable reordering
      panel.$el
        .find(".elementor-repeater-row-tools .elementor-repeater-tool-move")
        .hide();

      // Find the "Add Item" button wrapper container and drop it
      panel.$el
        .find(".phab-nested-accordion-repeater .elementor-repeater-add")
        .remove();
    },
  );
});
