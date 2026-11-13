import 'package:flutter/material.dart';

/// Swipeable product photos for catalog cards.
class ProductPhotoCarousel extends StatefulWidget {
  const ProductPhotoCarousel({
    super.key,
    required this.images,
    required this.placeholder,
    this.fit = BoxFit.cover,
  });

  final List<String> images;
  final Widget placeholder;
  final BoxFit fit;

  @override
  State<ProductPhotoCarousel> createState() => _ProductPhotoCarouselState();
}

class _ProductPhotoCarouselState extends State<ProductPhotoCarousel> {
  late final PageController _controller;
  int _page = 0;

  List<String> get _images => widget.images
      .map((image) => image.trim())
      .where((image) => image.isNotEmpty)
      .toSet()
      .toList(growable: false);

  @override
  void initState() {
    super.initState();
    _controller = PageController();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(covariant ProductPhotoCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.images.join('\n') != widget.images.join('\n')) {
      _page = 0;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _controller.hasClients) _controller.jumpToPage(0);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final images = _images;
    if (images.isEmpty) return widget.placeholder;

    return Stack(
      fit: StackFit.expand,
      children: [
        PageView.builder(
          controller: _controller,
          itemCount: images.length,
          onPageChanged: (index) => setState(() => _page = index),
          itemBuilder: (context, index) => Image.network(
            images[index],
            fit: widget.fit,
            errorBuilder: (context, error, stackTrace) => widget.placeholder,
          ),
        ),
        if (images.length > 1)
          Positioned(
            right: 7,
            bottom: 7,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.black54,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                child: Text(
                  '${_page + 1}/${images.length}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
