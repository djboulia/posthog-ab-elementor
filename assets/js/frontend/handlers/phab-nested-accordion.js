// this will wait to load both the class as well as the handler until
// after the frontend is initialized.  If the class is defined outside
// of this listener, the elementorModules may not be available yet and the class definition will fail
window.addEventListener("elementor/frontend/init", () => {
  class PhabNestedAccordion extends elementorModules.frontend.handlers.Base {
    constructor(...args) {
      super(...args);

      this.animations = new Map();
    }

    getDefaultSettings() {
      return {
        selectors: {
          accordion: ".e-n-accordion",
          accordionContentContainers: ".e-n-accordion > .e-con",
          accordionItems: ".e-n-accordion-item",
          accordionItemTitles: ".e-n-accordion-item-title",
          accordionItemTitlesText: ".e-n-accordion-item-title-text",
          accordionContent: ".e-n-accordion-item > .e-con",
          directAccordionItems: ":scope > .e-n-accordion-item",
          directAccordionItemTitles:
            ":scope > .e-n-accordion-item > .e-n-accordion-item-title",
        },
        default_state: "expanded",
        attributes: {
          index: "data-accordion-index",
          ariaLabelledBy: "aria-labelledby",
        },
      };
    }

    getDefaultElements() {
      const selectors = this.getSettings("selectors");

      return {
        $accordion: this.findElement(selectors.accordion),
        $contentContainers: this.findElement(
          selectors.accordionContentContainers,
        ),
        $accordionItems: this.findElement(selectors.accordionItems),
        $accordionTitles: this.findElement(selectors.accordionItemTitles),
        $accordionContent: this.findElement(selectors.accordionContent),
      };
    }

    onInit(...args) {
      console.log(
        "phab: Initializing frontend handler for phab-nested-accordion with settings:",
        this.getSettings(),
      );
      super.onInit(...args);
    }

    linkContainer(event) {
      const {
          container,
          index,
          targetContainer,
          action: { type },
        } = event.detail,
        view = container.view.$el,
        id = container.model.get("id"),
        currentId = this.$element.data("id");

      if (id === currentId) {
        const { $accordionItems } = this.getDefaultElements();

        let accordionItem, contentContainer;

        switch (type) {
          case "move":
            [accordionItem, contentContainer] = this.move(
              view,
              index,
              targetContainer,
              $accordionItems,
            );
            break;
          case "duplicate":
            [accordionItem, contentContainer] = this.duplicate(
              view,
              index,
              targetContainer,
              $accordionItems,
            );
            break;
          default:
            break;
        }

        if (undefined !== accordionItem) {
          accordionItem.appendChild(contentContainer);
        }

        this.updateIndexValues();
        this.updateListeners(view);

        elementor.$preview[0].contentWindow.dispatchEvent(
          new CustomEvent("elementor/elements/link-data-bindings"),
        );
      }
    }

    move(view, index, targetContainer, accordionItems) {
      return [accordionItems[index], targetContainer.view.$el[0]];
    }

    duplicate(view, index, targetContainer, accordionItems) {
      return [accordionItems[index + 1], targetContainer.view.$el[0]];
    }

    updateIndexValues() {
      const { $accordionContent, $accordionItems } = this.getDefaultElements(),
        settings = this.getSettings(),
        itemIdBase = $accordionItems[0].getAttribute("id").slice(0, -1);

      $accordionItems.each((index, element) => {
        element.setAttribute("id", `${itemIdBase}${index}`);
        element
          .querySelector(settings.selectors.accordionItemTitles)
          .setAttribute(settings.attributes.index, index + 1);
        element
          .querySelector(settings.selectors.accordionItemTitles)
          .setAttribute("aria-controls", `${itemIdBase}${index}`);
        element
          .querySelector(settings.selectors.accordionItemTitlesText)
          .setAttribute("data-binding-index", index + 1);
        $accordionContent[index].setAttribute(
          settings.attributes.ariaLabelledBy,
          `${itemIdBase}${index}`,
        );
      });
    }

    updateListeners(view) {
      this.elements.$accordionTitles = view.find(
        this.getSettings("selectors.accordionItemTitles"),
      );
      this.elements.$accordionItems = view.find(
        this.getSettings("selectors.accordionItems"),
      );
    }

    bindEvents() {
      elementorFrontend.elements.$window.on(
        "elementor/nested-container/atomic-repeater",
        this.linkContainer.bind(this),
      );
    }

    unbindEvents() {
      this.elements.$accordionTitles.off();
    }

    animateItem(accordionItem, startHeight, endHeight, isOpen) {
      accordionItem.style.overflow = "hidden";
      let animation = this.animations.get(accordionItem);

      if (animation) {
        animation.cancel();
      }

      animation = accordionItem.animate(
        { height: [startHeight, endHeight] },
        { duration: this.getAnimationDuration() },
      );

      animation.onfinish = () => this.onAnimationFinish(accordionItem, isOpen);
      this.animations.set(accordionItem, animation);

      accordionItem
        .querySelector("summary")
        ?.setAttribute("aria-expanded", isOpen);
    }

    closeAccordionItem(accordionItem, accordionItemTitle) {
      const startHeight = `${accordionItem.offsetHeight}px`,
        endHeight = `${accordionItemTitle.offsetHeight}px`;

      this.animateItem(accordionItem, startHeight, endHeight, false);
    }

    prepareOpenAnimation(
      accordionItem,
      accordionItemTitle,
      accordionItemContent,
    ) {
      accordionItem.style.overflow = "hidden";
      accordionItem.style.height = `${accordionItem.offsetHeight}px`;
      accordionItem.open = true;
      window.requestAnimationFrame(() =>
        this.openAccordionItem(
          accordionItem,
          accordionItemTitle,
          accordionItemContent,
        ),
      );
    }

    openAccordionItem(accordionItem, accordionItemTitle, accordionItemContent) {
      const { offsetHeight: accordionItemHeight } = accordionItem;
      const { offsetHeight: accordionItemTitleHeight } = accordionItemTitle;
      const { offsetHeight: accordionItemContentHeight } = accordionItemContent;
      if (
        !accordionItemHeight ||
        !accordionItemTitleHeight ||
        !accordionItemContentHeight
      ) {
        return;
      }

      this.animateItem(
        accordionItem,
        `${accordionItemHeight}px`,
        `${accordionItemTitleHeight + accordionItemContentHeight}px`,
        true,
      );
    }

    onAnimationFinish(accordionItem, isOpen) {
      accordionItem.open = isOpen;
      this.animations.set(accordionItem, null);
      accordionItem.style.height = accordionItem.style.overflow = "";
    }

    closeAllItems(items, titles) {
      titles.forEach((title, index) => {
        this.closeAccordionItem(items[index], title);
      });
    }

    getAnimationDuration() {
      return 400; // duration in ms
    }
  }

  console.log(
    `Elementor frontend initialized for phab-nested-accordion`,
    elementorFrontend,
  );

  // this is the new, recommended way to attach a handler to an elementor widget.
  // https://developers.elementor.com/a-new-method-for-attaching-a-js-handler-to-an-elementor-widget/
  elementorFrontend.elementsHandler.attachHandler(
    "phab-nested-accordion",
    PhabNestedAccordion,
  );
});
