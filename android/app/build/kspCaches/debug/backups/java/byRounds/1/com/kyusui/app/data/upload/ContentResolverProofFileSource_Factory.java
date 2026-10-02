package com.kyusui.app.data.upload;

import android.content.ContentResolver;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata("javax.inject.Singleton")
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class ContentResolverProofFileSource_Factory implements Factory<ContentResolverProofFileSource> {
  private final Provider<ContentResolver> contentResolverProvider;

  public ContentResolverProofFileSource_Factory(Provider<ContentResolver> contentResolverProvider) {
    this.contentResolverProvider = contentResolverProvider;
  }

  @Override
  public ContentResolverProofFileSource get() {
    return newInstance(contentResolverProvider.get());
  }

  public static ContentResolverProofFileSource_Factory create(
      Provider<ContentResolver> contentResolverProvider) {
    return new ContentResolverProofFileSource_Factory(contentResolverProvider);
  }

  public static ContentResolverProofFileSource newInstance(ContentResolver contentResolver) {
    return new ContentResolverProofFileSource(contentResolver);
  }
}
