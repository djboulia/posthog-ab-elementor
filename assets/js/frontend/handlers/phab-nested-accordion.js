// this will wait to load both the class as well as the handler until
// after the frontend is initialized.  If the class is defined outside
// of this listener, the elementorModules may not be available yet and the class definition will fail
window.addEventListener("elementor/frontend/init", () => {
  class NestedTitleKeyboardHandler
    extends elementorModules.frontend.handlers.Base
  {
    __construct(settings) {
      super.__construct(settings);

      this.directionNext = "next";
      this.directionPrevious = "previous";
      this.focusableElementSelector =
        'audio, button, canvas, details, iframe, input, select, summary, textarea, video, [accesskey], [contenteditable], [href], [tabindex]:not([tabindex="-1"])';
    }

    getWidgetNumber() {
      return this.$element
        .find("> .elementor-widget-container > .e-n-tabs, > .e-n-tabs")
        .attr("data-widget-number");
    }

    getDefaultSettings() {
      return {
        selectors: {
          itemTitle: `[data-tab-title-id*="e-n-tab-title-${this.getWidgetNumber()}"]`,
          itemContainer: `[id*="e-n-tab-content-${this.getWidgetNumber()}"]`,
        },
        ariaAttributes: {
          titleStateAttribute: "aria-selected",
          activeTitleSelector: '[aria-selected="true"]',
        },
        datasets: {
          titleIndex: "data-tab-index",
        },
        keyDirection: {
          ArrowLeft: elementorFrontendConfig.is_rtl
            ? this.directionNext
            : this.directionPrevious,
          ArrowUp: this.directionPrevious,
          ArrowRight: elementorFrontendConfig.is_rtl
            ? this.directionPrevious
            : this.directionNext,
          ArrowDown: this.directionNext,
        },
      };
    }

    getDefaultElements() {
      const selectors = this.getSettings("selectors");

      return {
        $itemTitles: this.findElement(selectors.itemTitle),
        $itemContainers: this.findElement(selectors.itemContainer),
        $focusableContainerElements: this.getFocusableElements(
          this.findElement(selectors.itemContainer),
        ),
      };
    }

    getFocusableElements($elements) {
      return $elements
        .find(this.focusableElementSelector)
        .not("[disabled], [inert]");
    }

    getKeyDirectionValue(event) {
      const direction = this.getSettings("keyDirection")[event.key];
      return this.directionNext === direction ? 1 : -1;
    }

    /**
     * @param {HTMLElement} itemTitleElement
     *
     * @return {string}
     */
    getTitleIndex(itemTitleElement) {
      const { titleIndex: indexAttribute } = this.getSettings("datasets");
      return itemTitleElement.getAttribute(indexAttribute);
    }

    /**
     * @param {string|number} titleIndex
     *
     * @return {string}
     */
    getTitleFilterSelector(titleIndex) {
      const { titleIndex: indexAttribute } = this.getSettings("datasets");
      return `[${indexAttribute}="${titleIndex}"]`;
    }

    getActiveTitleElement() {
      const activeTitleFilter =
        this.getSettings("ariaAttributes").activeTitleSelector;
      return this.elements.$itemTitles.filter(activeTitleFilter);
    }

    onInit(...args) {
      super.onInit(...args);
    }

    bindEvents() {
      this.elements.$itemTitles.on(this.getTitleEvents());
      this.elements.$focusableContainerElements.on(
        this.getContentElementEvents(),
      );
    }

    unbindEvents() {
      this.elements.$itemTitles.off(this.getTitleEvents());
      this.elements.$focusableContainerElements
        .children()
        .off(this.getContentElementEvents());
    }

    getTitleEvents() {
      return {
        keydown: this.handleTitleKeyboardNavigation.bind(this),
      };
    }

    getContentElementEvents() {
      return {
        keydown: this.handleContentElementKeyboardNavigation.bind(this),
      };
    }

    isDirectionKey(event) {
      const directionKeys = [
        "ArrowLeft",
        "ArrowRight",
        "ArrowUp",
        "ArrowDown",
        "Home",
        "End",
      ];
      return directionKeys.includes(event.key);
    }

    isActivationKey(event) {
      const activationKeys = ["Enter", " "];
      return activationKeys.includes(event.key);
    }

    handleTitleKeyboardNavigation(event) {
      if (this.isDirectionKey(event)) {
        event.preventDefault();

        const currentTitleIndex =
            parseInt(this.getTitleIndex(event.currentTarget)) || 1,
          numberOfTitles = this.elements.$itemTitles.length,
          titleIndexUpdated = this.getTitleIndexFocusUpdated(
            event,
            currentTitleIndex,
            numberOfTitles,
          );

        this.changeTitleFocus(titleIndexUpdated);
        event.stopPropagation();
      } else if (this.isActivationKey(event)) {
        event.preventDefault();

        if (this.handeTitleLinkEnterOrSpaceEvent(event)) {
          return;
        }

        const titleIndex = this.getTitleIndex(event.currentTarget);

        elementorFrontend.elements.$window.trigger(
          "elementor/nested-elements/activate-by-keyboard",
          { widgetId: this.getID(), titleIndex },
        );
      } else if ("Escape" === event.key) {
        this.handleTitleEscapeKeyEvents(event);
      }
    }

    handeTitleLinkEnterOrSpaceEvent(event) {
      const isLinkElement =
        "a" === event?.currentTarget?.tagName?.toLowerCase();

      if (!elementorFrontend.isEditMode() && isLinkElement) {
        event?.currentTarget?.click();
        event.stopPropagation();
      }

      return isLinkElement;
    }

    getTitleIndexFocusUpdated(event, currentTitleIndex, numberOfTitles) {
      let titleIndexUpdated = 0;

      switch (event.key) {
        case "Home":
          titleIndexUpdated = 1;
          break;
        case "End":
          titleIndexUpdated = numberOfTitles;
          break;
        default:
          const directionValue = this.getKeyDirectionValue(event),
            isEndReached = numberOfTitles < currentTitleIndex + directionValue,
            isStartReached = 0 === currentTitleIndex + directionValue;

          if (isEndReached) {
            titleIndexUpdated = 1;
          } else if (isStartReached) {
            titleIndexUpdated = numberOfTitles;
          } else {
            titleIndexUpdated = currentTitleIndex + directionValue;
          }
      }

      return titleIndexUpdated;
    }

    changeTitleFocus(titleIndexUpdated) {
      const $newTitle = this.elements.$itemTitles.filter(
        this.getTitleFilterSelector(titleIndexUpdated),
      );

      this.setTitleTabindex(titleIndexUpdated);

      $newTitle.trigger("focus");
    }

    setTitleTabindex(titleIndex) {
      this.elements.$itemTitles.attr("tabindex", "-1");

      const $newTitle = this.elements.$itemTitles.filter(
        this.getTitleFilterSelector(titleIndex),
      );

      $newTitle.attr("tabindex", "0");
    }

    handleTitleEscapeKeyEvents() {}

    handleContentElementKeyboardNavigation(event) {
      if ("Tab" === event.key && !event.shiftKey) {
        this.handleContentElementTabEvents(event);
      } else if ("Escape" === event.key) {
        event.preventDefault();
        event.stopPropagation();
        this.handleContentElementEscapeEvents(event);
      }
    }

    handleContentElementEscapeEvents() {
      this.getActiveTitleElement().trigger("focus");
    }

    handleContentElementTabEvents() {}
  }

  class PhabNestedAccordionTitleKeyboardHandler extends NestedTitleKeyboardHandler {
    __construct(...args) {
      super.__construct(...args);
      const config = args[0];
      this.toggleTitle = config.toggleTitle;
    }

    getDefaultSettings() {
      const parentSettings = super.getDefaultSettings();

      return {
        ...parentSettings,
        selectors: {
          itemTitle: ".e-n-accordion-item-title",
          itemContainer: ".e-n-accordion-item > .e-con",
        },
        ariaAttributes: {
          titleStateAttribute: "aria-expanded",
          activeTitleSelector: '[aria-expanded="true"]',
        },
        datasets: {
          titleIndex: "data-accordion-index",
        },
      };
    }

    handeTitleLinkEnterOrSpaceEvent(event) {
      this.toggleTitle(event);
    }

    handleContentElementEscapeEvents(event) {
      this.getActiveTitleElement().trigger("focus");
      this.toggleTitle(event);
    }

    handleTitleEscapeKeyEvents(event) {
      const detailsNode = event?.currentTarget?.parentElement,
        isOpen = detailsNode?.open;

      if (isOpen) {
        this.toggleTitle(event);
      }
    }
  }

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
      super.onInit(...args);

      this.injectKeyboardHandler();
    }

    injectKeyboardHandler() {
      if ("nested-accordion.default" === this.getSettings("elementName")) {
        new PhabNestedAccordionTitleKeyboardHandler({
          $element: this.$element,
          toggleTitle: this.clickListener.bind(this),
        });
      }
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
      this.elements.$accordionTitles.on("click", this.clickListener.bind(this));
    }

    bindEvents() {
      this.elements.$accordionTitles.on("click", this.clickListener.bind(this));
      elementorFrontend.elements.$window.on(
        "elementor/nested-container/atomic-repeater",
        this.linkContainer.bind(this),
      );
    }

    unbindEvents() {
      this.elements.$accordionTitles.off();
    }

    clickListener(event) {
      event.preventDefault();
      this.elements = this.getDefaultElements();

      const settings = this.getSettings(),
        accordionItem = event?.currentTarget?.closest(
          settings.selectors.accordionItems,
        ),
        accordion = event?.currentTarget?.closest(settings.selectors.accordion),
        itemSummary = accordionItem.querySelector(
          settings.selectors.accordionItemTitles,
        ),
        accordionContent = accordionItem.querySelector(
          settings.selectors.accordionContent,
        ),
        { max_items_expended: maxItemsExpended } = this.getElementSettings(),
        directAccordionItems = accordion.querySelectorAll(
          settings.selectors.directAccordionItems,
        ),
        directAccordionItemTitles = accordion.querySelectorAll(
          settings.selectors.directAccordionItemTitles,
        );

      if ("one" === maxItemsExpended) {
        this.closeAllItems(directAccordionItems, directAccordionItemTitles);
      }

      if (!accordionItem.open) {
        this.prepareOpenAnimation(accordionItem, itemSummary, accordionContent);
      } else {
        this.closeAccordionItem(accordionItem, itemSummary);
      }
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
      const { size, unit } = this.getElementSettings(
        "n_accordion_animation_duration",
      );
      return size * ("ms" === unit ? 1 : 1000);
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
