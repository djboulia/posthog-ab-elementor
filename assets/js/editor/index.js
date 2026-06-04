console.log(`Elementor loaded editor phab-nested-accordion `);

elementorCommon.elements.$window.on(
  "elementor/nested-element-type-loaded",
  async () => {
    console.log("phab nested-element-type-loaded event fired"); // works!

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

console.log(`Elementor editor initialized for phab-nested-accordion`);
